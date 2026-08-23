<?php

namespace App\Services;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentBalanceLedger;
use App\Models\BookingAttempt;
use App\Models\Deposit\Deposit;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentBalanceService
{
    public const EVENT_CREDIT_APPROVED = 'credit_approved';
    public const EVENT_DEPOSIT_APPROVED = 'deposit_approved';
    public const EVENT_DEPOSIT_CREDIT_ADJUST = 'deposit_credit_adjust';
    public const EVENT_BOOKING_DEBIT = 'booking_debit';
    public const EVENT_VOID_REFUND = 'void_refund';

    public function getBalances(int $agentId): array
    {
        $agent = Agent::findOrFail($agentId);
        $net = (float) ($agent->net_balance ?? 0);
        $credit = (float) ($agent->credit_balance ?? 0);
        $reserved = (float) ($agent->reserved_balance ?? 0);

        $creditTakenTotal = (float) AgentBalanceLedger::query()
            ->where('agent_id', $agentId)
            ->where('event_type', self::EVENT_CREDIT_APPROVED)
            ->sum('amount');

        $cashDepositedTotal = (float) AgentBalanceLedger::query()
            ->where('agent_id', $agentId)
            ->whereIn('event_type', [self::EVENT_DEPOSIT_APPROVED, self::EVENT_DEPOSIT_CREDIT_ADJUST])
            ->sum('amount');

        return [
            'net_balance' => $net,
            'credit_balance' => $credit,
            'reserved_balance' => $reserved,
            'available_balance' => $net - $reserved,
            'cash_portion' => $net - $credit,
            'credit_taken_total' => $creditTakenTotal,
            'cash_deposited_total' => $cashDepositedTotal,
        ];
    }

    // Money held for a booking that is mid-issuance is already spoken for, so it is not
    // spendable even though it is still sitting in net_balance
    public function availableBalance(Agent $agent): float
    {
        return (float) ($agent->net_balance ?? 0) - (float) ($agent->reserved_balance ?? 0);
    }

    public function assertSufficientBalance(Agent $agent, float $amount): void
    {
        $available = $this->availableBalance($agent);
        if ($amount > $available) {
            throw $this->insufficientBalance($available, $amount);
        }
    }

    public function approveDeposit(Deposit $depo, ?string $adjustCredit, ?int $userId): void
    {
        $agent = Agent::where('id', $depo->agent_id)->lockForUpdate()->firstOrFail();
        $total = (float) $depo->total;
        $netBefore = (float) ($agent->net_balance ?? 0);
        $creditBefore = (float) ($agent->credit_balance ?? 0);

        if ($this->isCreditRequest($depo->type)) {
            $agent->net_balance = $netBefore + $total;
            $agent->credit_balance = $creditBefore + $total;
            $eventType = self::EVENT_CREDIT_APPROVED;
            $description = 'Credit request approved';
        } elseif ($adjustCredit === 'Yes') {
            $clear = min($total, $creditBefore);
            $agent->credit_balance = $creditBefore - $clear;
            $agent->net_balance = $netBefore + $total - $clear;
            $eventType = self::EVENT_DEPOSIT_CREDIT_ADJUST;
            $description = ($depo->type ?? 'Deposit') . ' approved (credit adjusted)';
        } else {
            $agent->net_balance = $netBefore + $total;
            $eventType = self::EVENT_DEPOSIT_APPROVED;
            $description = ($depo->type ?? 'Deposit') . ' approved';
        }

        $agent->save();

        $this->writeLedger($agent, [
            'event_type' => $eventType,
            'amount' => $total,
            'direction' => 'in',
            'net_before' => $netBefore,
            'credit_before' => $creditBefore,
            'net_after' => (float) $agent->net_balance,
            'credit_after' => (float) $agent->credit_balance,
            'reference_type' => 'deposit',
            'reference_id' => $depo->id,
            'description' => $description,
            'metadata' => [
                'deposit_type' => $depo->type,
                'adjust_credit' => $adjustCredit,
            ],
            'user_id' => $userId,
        ]);
    }

    // Called before the GDS is touched. The balance check and the hold are one atomic statement,
    // so a second request in another browser sees the reduced availability instead of the stale
    // pre-booking figure — the window that let two tickets share one balance.
    public function reserveForBooking(Agent $agent, float $amount, BookingAttempt $attempt, ?int $userId = null): void
    {
        if ($amount <= 0) {
            throw new Exception('Booking amount could not be determined.');
        }

        // A hold already standing on this attempt is an earlier run that crashed, or a retry —
        // reuse it rather than stacking a second hold on top
        if ($attempt->balance_reserved_at !== null) {
            return;
        }

        $held = $this->money($amount);

        $affected = Agent::query()
            ->whereKey($agent->id)
            ->whereRaw('(COALESCE(net_balance, 0) - COALESCE(reserved_balance, 0)) >= ?', [$amount])
            ->update(['reserved_balance' => DB::raw("COALESCE(reserved_balance, 0) + {$held}")]);

        if ($affected !== 1) {
            $agent->refresh();
            throw $this->insufficientBalance($this->availableBalance($agent), $amount);
        }

        $attempt->forceFill([
            'reserved_amount'     => $amount,
            'balance_reserved_at' => now(),
        ])->save();

        $agent->refresh();
    }

    // Ticketing failed, or returned documents that were already issued — the held funds were
    // never spent, so hand them back. Safe to call when nothing is held.
    public function releaseReservation(Agent $agent, BookingAttempt $attempt, ?int $userId = null): void
    {
        if ($attempt->balance_reserved_at === null) {
            return;
        }

        $amount = (float) ($attempt->reserved_amount ?? 0);
        $held   = $this->money($amount);

        DB::transaction(function () use ($agent, $attempt, $held) {
            Agent::query()
                ->whereKey($agent->id)
                ->update([
                    'reserved_balance' => DB::raw("GREATEST(COALESCE(reserved_balance, 0) - {$held}, 0)"),
                ]);

            $attempt->forceFill([
                'reserved_amount'     => null,
                'balance_reserved_at' => null,
            ])->save();
        });

        $agent->refresh();
    }

    // Tickets are issued: turn the hold into a real debit. net_balance and reserved_balance move
    // together in one statement so the agent never sees the money counted twice or not at all.
    public function settleReservation(Agent $agent, BookingAttempt $attempt, ?int $userId = null): void
    {
        $amount = (float) ($attempt->reserved_amount ?? 0);

        // No hold — an attempt from before reservations existed, or one issued through a path
        // that skipped the reserve. Fall back to a locked debit so the ticket is still paid for.
        if ($attempt->balance_reserved_at === null || $amount <= 0) {
            $this->debitForBooking($agent, $this->resolveBookingAmount($attempt), $attempt, $userId);

            return;
        }

        if ($this->hasBookingDebit($attempt->id)) {
            $this->releaseReservation($agent, $attempt, $userId);

            return;
        }

        $held = $this->money($amount);

        DB::transaction(function () use ($agent, $attempt, $amount, $held, $userId) {
            $locked = Agent::where('id', $agent->id)->lockForUpdate()->firstOrFail();

            $netBefore    = (float) ($locked->net_balance ?? 0);
            $creditBefore = (float) ($locked->credit_balance ?? 0);

            Agent::query()
                ->whereKey($agent->id)
                ->update([
                    'net_balance'      => DB::raw("net_balance - {$held}"),
                    'reserved_balance' => DB::raw("GREATEST(COALESCE(reserved_balance, 0) - {$held}, 0)"),
                ]);

            $locked->refresh();

            $pnr = $attempt->gds_pnr ?? $attempt->airline_pnr ?? '';

            $this->writeLedger($locked, [
                'event_type' => self::EVENT_BOOKING_DEBIT,
                'amount' => $amount,
                'direction' => 'out',
                'net_before' => $netBefore,
                'credit_before' => $creditBefore,
                'net_after' => (float) $locked->net_balance,
                'credit_after' => $creditBefore,
                'reference_type' => 'booking_attempt',
                'reference_id' => $attempt->id,
                'description' => $pnr ? "Ticket booking (PNR: {$pnr})" : 'Ticket booking',
                'metadata' => [
                    'booking_attempt_id' => $attempt->id,
                    'gds_pnr' => $attempt->gds_pnr,
                ],
                'user_id' => $userId,
            ]);

            $attempt->forceFill([
                'reserved_amount'     => null,
                'balance_reserved_at' => null,
            ])->save();
        });

        $agent->refresh();
    }

    public function debitForBooking(Agent $agent, float $amount, BookingAttempt $attempt, ?int $userId): void
    {
        if ($this->hasBookingDebit($attempt->id)) {
            return;
        }

        DB::transaction(function () use ($agent, $amount, $attempt, $userId) {
            $agent = Agent::where('id', $agent->id)->lockForUpdate()->firstOrFail();
            $this->assertSufficientBalance($agent, $amount);

            $netBefore = (float) ($agent->net_balance ?? 0);
            $creditBefore = (float) ($agent->credit_balance ?? 0);

            $agent->net_balance = $netBefore - $amount;
            $agent->save();

            $pnr = $attempt->gds_pnr ?? $attempt->airline_pnr ?? '';

            $this->writeLedger($agent, [
                'event_type' => self::EVENT_BOOKING_DEBIT,
                'amount' => $amount,
                'direction' => 'out',
                'net_before' => $netBefore,
                'credit_before' => $creditBefore,
                'net_after' => (float) $agent->net_balance,
                'credit_after' => $creditBefore,
                'reference_type' => 'booking_attempt',
                'reference_id' => $attempt->id,
                'description' => $pnr ? "Ticket booking (PNR: {$pnr})" : 'Ticket booking',
                'metadata' => [
                    'booking_attempt_id' => $attempt->id,
                    'gds_pnr' => $attempt->gds_pnr,
                ],
                'user_id' => $userId,
            ]);
        });
    }

    public function creditForVoid(Agent $agent, float $amount, BookingAttempt $attempt, ?int $userId): void
    {
        DB::transaction(function () use ($agent, $amount, $attempt, $userId) {
            $agent = Agent::where('id', $agent->id)->lockForUpdate()->firstOrFail();

            $netBefore    = (float) ($agent->net_balance ?? 0);
            $creditBefore = (float) ($agent->credit_balance ?? 0);

            $agent->net_balance = $netBefore + $amount;
            $agent->save();

            $pnr = $attempt->gds_pnr ?? $attempt->airline_pnr ?? '';

            $this->writeLedger($agent, [
                'event_type'     => self::EVENT_VOID_REFUND,
                'amount'         => $amount,
                'direction'      => 'in',
                'net_before'     => $netBefore,
                'credit_before'  => $creditBefore,
                'net_after'      => (float) $agent->net_balance,
                'credit_after'   => $creditBefore,
                'reference_type' => 'booking_attempt',
                'reference_id'   => $attempt->id,
                'description'    => $pnr ? "Ticket void refund (PNR: {$pnr})" : 'Ticket void refund',
                'metadata'       => [
                    'booking_attempt_id' => $attempt->id,
                    'gds_pnr'            => $attempt->gds_pnr,
                ],
                'user_id'        => $userId,
            ]);
        });
    }

    public function getStatement(int $agentId, ?string $from = null, ?string $to = null, int $perPage = 20)
    {
        $q = AgentBalanceLedger::query()
            ->where('agent_id', $agentId)
            ->orderByDesc('transaction_at')
            ->orderByDesc('id');

        if ($from) {
            $q->whereDate('transaction_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('transaction_at', '<=', $to);
        }

        return $q->paginate($perPage);
    }

    public function resolveBookingAmount(BookingAttempt $attempt): float
    {
        $snapshot = $attempt->snapshot_json;
        if (is_array($snapshot)) {
            $fromSnapshot = data_get($snapshot, 'price.total_price');
            if ($fromSnapshot !== null && $fromSnapshot !== '') {
                return (float) $fromSnapshot;
            }
        }

        $attempt->loadMissing('priceLog');
        if ($attempt->priceLog?->total_price) {
            return (float) $attempt->priceLog->total_price;
        }

        throw new Exception('Booking amount could not be determined.');
    }

    public function resolveAgentForUser(?int $userId): ?Agent
    {
        if (!$userId) {
            return null;
        }

        // Sub-users hang off their agency through users.agent_id. agents.user_id is legacy and
        // only points at whichever user happened to be created with the agency, so resolving by
        // it leaves every other sub-user of the same agency without a wallet.
        $agentId = User::whereKey($userId)->value('agent_id');

        if ($agentId) {
            return Agent::find($agentId);
        }

        return Agent::where('user_id', $userId)->first();
    }

    public function hasBookingDebit(int $bookingAttemptId): bool
    {
        return AgentBalanceLedger::query()
            ->where('reference_type', 'booking_attempt')
            ->where('reference_id', $bookingAttemptId)
            ->where('event_type', self::EVENT_BOOKING_DEBIT)
            ->exists();
    }

    private function insufficientBalance(float $available, float $amount): Exception
    {
        return new Exception(sprintf(
            'Insufficient balance. Available: ৳%s, Required: ৳%s',
            number_format($available, 2, '.', ','),
            number_format($amount, 2, '.', ',')
        ));
    }

    // Amounts go into the SQL expression as a fixed-scale literal: the value is already a float
    // cast, and formatting it keeps the arithmetic at the column's own precision
    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function isCreditRequest(?string $type): bool
    {
        return in_array($type, ['Credit Request', 'Credit_Request'], true);
    }

    private function writeLedger(Agent $agent, array $data): void
    {
        AgentBalanceLedger::create([
            'agent_id' => $agent->id,
            'event_type' => $data['event_type'],
            'amount' => $data['amount'],
            'direction' => $data['direction'],
            'net_balance_before' => $data['net_before'],
            'net_balance_after' => $data['net_after'],
            'credit_balance_before' => $data['credit_before'],
            'credit_balance_after' => $data['credit_after'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'description' => $data['description'],
            'metadata' => $data['metadata'] ?? null,
            'transaction_at' => now(),
            'created_by' => $data['user_id'],
            'updated_by' => $data['user_id'],
        ]);
    }
}
