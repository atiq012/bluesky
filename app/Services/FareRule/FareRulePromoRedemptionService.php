<?php

namespace App\Services\FareRule;

use App\Models\Agent\Agent;
use App\Models\BookingAttempt;
use App\Models\FareRule\FareRule;
use App\Models\FareRule\FareRulePromoRedemption;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

// §7.6 — promo used_count is counted at booking commit and released on cancel/void; issue does
// nothing (already counted at commit). Best-effort by design, same precedent as the ticketing-
// deadline sync in TpV2CommitService: a promo-accounting glitch must never block a real GDS
// commit/cancel/void, so every public method here swallows and logs instead of throwing.
class FareRulePromoRedemptionService
{
    public function __construct(private readonly FareRuleCache $cache)
    {
    }

    public function redeem(BookingAttempt $attempt): void
    {
        $promo = $this->promoApplied($attempt);
        if ($promo === null || empty($promo['rule_id'])) {
            return;
        }

        try {
            DB::transaction(function () use ($attempt, $promo) {
                $redemption = new FareRulePromoRedemption([
                    'fare_rule_id'       => $promo['rule_id'],
                    'booking_attempt_id' => $attempt->id,
                    'agent_id'           => $this->resolveAgentId($attempt),
                    'amount'             => $promo['amount'] ?? 0,
                    'redeemed_at'        => now(),
                ]);
                $redemption->save();

                // Shares the transaction with the insert above — used_count can never drift from
                // the redemption row count (§7.6, §5.5).
                FareRule::whereKey($promo['rule_id'])->increment('used_count');
            });

            // increment() is a raw query-builder update — it never fires model events, so
            // FareRuleObserver never runs. Without this, a promo could sit exhausted-in-the-DB
            // but not-yet-exhausted-in-cache until some unrelated rule edit happened to
            // invalidate it (the exact class of bug §11.7 exists to catch). Outside the
            // transaction, same as the observer's afterCommit rule (§11.4) — never invalidate
            // against rows that might still roll back.
            $this->cache->invalidate();
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                // Unique (fare_rule_id, booking_attempt_id) — a retried commit (retry-commit
                // endpoint, or a re-thrown-then-retried request) lands here and correctly no-ops
                // rather than double-counting.
                return;
            }

            Log::warning('FareRuleEngine: promo redemption failed', [
                'attempt_id' => $attempt->id, 'rule_id' => $promo['rule_id'], 'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::warning('FareRuleEngine: promo redemption failed', [
                'attempt_id' => $attempt->id, 'rule_id' => $promo['rule_id'], 'error' => $e->getMessage(),
            ]);
        }
    }

    public function release(BookingAttempt $attempt): void
    {
        try {
            $released = DB::transaction(function () use ($attempt) {
                $redemptions = FareRulePromoRedemption::where('booking_attempt_id', $attempt->id)->get();

                foreach ($redemptions as $redemption) {
                    $redemption->delete();
                    FareRule::whereKey($redemption->fare_rule_id)->decrement('used_count');
                }

                return $redemptions->isNotEmpty();
            });

            if ($released) {
                $this->cache->invalidate();
            }
        } catch (Throwable $e) {
            Log::warning('FareRuleEngine: promo release failed', [
                'attempt_id' => $attempt->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    // Only populated when the engine flag is on (§ Phase 7) — FareRuleSearchIntegration is the
    // only writer of `fare_pricing`, so this is naturally a no-op with the flag off.
    private function promoApplied(BookingAttempt $attempt): ?array
    {
        $applied = data_get($attempt->priceLog?->price_payload, 'mapped.fare_pricing.applied', []);

        foreach ((array) $applied as $entry) {
            if (($entry['type'] ?? null) === 'promo' && ($entry['role'] ?? null) === 'base') {
                return $entry;
            }
        }

        return null;
    }

    private function resolveAgentId(BookingAttempt $attempt): ?int
    {
        if (! $attempt->user_id) {
            return null;
        }

        return Agent::where('user_id', $attempt->user_id)->value('id');
    }

    private function isDuplicate(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
