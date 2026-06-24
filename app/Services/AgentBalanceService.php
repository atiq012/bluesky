<?php

namespace App\Services;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentBalanceLedger;
use App\Models\BookingAttempt;
use App\Models\Deposit\Deposit;
use Exception;
use Illuminate\Support\Facades\DB;

class AgentBalanceService
{
    public const EVENT_CREDIT_APPROVED = 'credit_approved';
    public const EVENT_DEPOSIT_APPROVED = 'deposit_approved';
    public const EVENT_DEPOSIT_CREDIT_ADJUST = 'deposit_credit_adjust';
    public const EVENT_BOOKING_DEBIT = 'booking_debit';

    public function getBalances(int $agentId): array
    {
        $agent = Agent::findOrFail($agentId);
        $net = (float) ($agent->net_balance ?? 0);
        $credit = (float) ($agent->credit_balance ?? 0);

        return [
            'net_balance' => $net,
            'credit_balance' => $credit,
            'cash_portion' => $net - $credit,
        ];
    }

    public function assertSufficientBalance(Agent $agent, float $amount): void
    {
        $available = (float) ($agent->net_balance ?? 0);
        if ($amount > $available) {
            throw new Exception(sprintf(
                'Insufficient balance. Available: ৳%s, Required: ৳%s',
                number_format($available, 2, '.', ','),
                number_format($amount, 2, '.', ',')
            ));
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
