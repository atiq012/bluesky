<?php

namespace App\Services\FareRule;

use Carbon\Carbon;
use Carbon\CarbonInterface;

// Does rule R match context C? Operates on the flat array shape produced by
// FareRuleSnapshotBuilder — never Eloquent (invariant §16.1-2).
class FareRuleMatcher
{
    private CarbonInterface $now;

    public function __construct(?CarbonInterface $now = null)
    {
        $this->now = $now ?? Carbon::now();
    }

    public function matches(array $rule, FareRuleContext $ctx): bool
    {
        return $this->explain($rule, $ctx) === null;
    }

    // Same checks as matches(), but returns the first failing check's reason code instead of a
    // bare bool — null means matched. Single source of truth for both matches() and
    // FareRuleSimulator's "why it missed" trace (§10.2) — never duplicate this logic elsewhere.
    public function explain(array $rule, FareRuleContext $ctx): ?string
    {
        if (($rule['status'] ?? 'active') !== 'active') {
            return 'status_inactive';
        }

        if (! $this->withinWindow($rule['effective_from'] ?? null, $rule['effective_to'] ?? null, $this->now->toDateString())) {
            return 'outside_effective_window';
        }

        if (
            $ctx->flightDate !== null && $ctx->flightDate !== ''
            && ! $this->withinWindow($rule['travel_from'] ?? null, $rule['travel_to'] ?? null, $ctx->flightDate)
        ) {
            return 'outside_travel_window';
        }

        // Airline is the top discriminator for fare logic; blank simulator airline should
        // not silently match airline-scoped rules, otherwise "Any" behaves like a wildcard.
        if (! $this->inList($rule['airlines'] ?? [], $ctx->carrier, false)) {
            return 'airline_mismatch';
        }

        if (! $this->inList($rule['suppliers'] ?? [], $ctx->supplier, $ctx->unspecifiedMeansAny)) {
            return 'supplier_mismatch';
        }

        if (! $this->matchesRoute($rule['routes'] ?? [], $ctx->origin, $ctx->destination, $ctx->unspecifiedMeansAny)) {
            return 'route_mismatch';
        }

        if (
            ($rule['onward'] ?? 'any') !== 'any'
            && $rule['onward'] !== $ctx->onward
            && ! $this->skipUnspecified($ctx->onward, $ctx->unspecifiedMeansAny)
        ) {
            return 'onward_mismatch';
        }

        if (
            ($rule['scope'] ?? 'any') !== 'any'
            && $rule['scope'] !== $ctx->scope
            && ! $this->skipUnspecified($ctx->scope, $ctx->unspecifiedMeansAny)
        ) {
            return 'scope_mismatch';
        }

        if (! $this->inList($rule['cabins'] ?? [], $ctx->cabin, $ctx->unspecifiedMeansAny)) {
            return 'cabin_mismatch';
        }

        if (! $this->inList($rule['booking_classes'] ?? [], $ctx->bookingClass, $ctx->unspecifiedMeansAny)) {
            return 'booking_class_mismatch';
        }

        // Invariant §16.1-8 — a rule scoped to tiers/agencies must NOT match a context with no agent.
        // Simulator "Any agency" is the exception: unspecifiedMeansAny skips the agent requirement
        // so a deal can be live-tested without picking a specific agency first.
        $tiers = $rule['tiers'] ?? [];
        if ($tiers !== []) {
            if (! $ctx->hasAgent) {
                if (! $ctx->unspecifiedMeansAny) {
                    return 'no_agent_in_context';
                }
            } elseif (! $this->inList($tiers, $ctx->agentTier, $ctx->unspecifiedMeansAny)) {
                return 'tier_mismatch';
            }
        }

        $agencies = $rule['agencies'] ?? [];
        if ($agencies !== []) {
            if (! $ctx->hasAgent) {
                if (! $ctx->unspecifiedMeansAny) {
                    return 'no_agent_in_context';
                }
            } elseif (! $this->inList($agencies, $ctx->agentId !== null ? (string) $ctx->agentId : null, $ctx->unspecifiedMeansAny)) {
                return 'agency_mismatch';
            }
        }

        if (($rule['type'] ?? null) === 'promo') {
            if (! empty($rule['promo_code'])) {
                $missing = $ctx->promoCode === null;
                $wrong   = ! $missing && strcasecmp($rule['promo_code'], $ctx->promoCode) !== 0;
                if ($wrong || ($missing && ! $ctx->unspecifiedMeansAny)) {
                    return 'promo_code_mismatch';
                }
            }

            if (($rule['usage_limit'] ?? null) !== null && ($rule['used_count'] ?? 0) >= $rule['usage_limit']) {
                return 'promo_usage_exhausted';
            }
        }

        if (($rule['type'] ?? null) === 'service_fee' && ! empty($rule['fee_event'])) {
            if (
                $rule['fee_event'] !== $ctx->feeEvent
                && ! $this->skipUnspecified($ctx->feeEvent, $ctx->unspecifiedMeansAny)
            ) {
                return 'fee_event_mismatch';
            }
        }

        return null;
    }

    // Simulator left a scalar blank ("Any") — don't fail a scoped rule for that.
    private function skipUnspecified(?string $value, bool $unspecifiedMeansAny): bool
    {
        return $unspecifiedMeansAny && ($value === null || $value === '');
    }

    // Empty list means Any (invariant §16.1-5). No 'all' sentinel — never reintroduce one.
    // unspecifiedMeansAny: simulator left this field blank ("Any") — skip the filter.
    private function inList(array $list, ?string $value, bool $unspecifiedMeansAny = false): bool
    {
        if ($list === []) {
            return true;
        }

        if ($value === null || $value === '') {
            return $unspecifiedMeansAny;
        }

        return in_array($value, $list, true);
    }

    // Each stored route row is one directional pair; '*' is a per-side wildcard.
    // ['DAC','*'] (ex-Dhaka) and ['*','DAC'] (into-Dhaka) are different rules, never collapsed.
    private function matchesRoute(array $pairs, string $origin, string $destination, bool $unspecifiedMeansAny = false): bool
    {
        if ($pairs === []) {
            return true;
        }

        // Simulator origin/dest left blank — don't fail route-scoped rules for that.
        if ($unspecifiedMeansAny && ($origin === '' || $destination === '')) {
            return true;
        }

        foreach ($pairs as $pair) {
            [$ro, $rd] = $pair;
            if (($ro === '*' || $ro === $origin) && ($rd === '*' || $rd === $destination)) {
                return true;
            }
        }

        return false;
    }

    private function withinWindow(?string $from, ?string $to, string $date): bool
    {
        $date = $this->dateOnly($date) ?? $date;
        $from = $this->dateOnly($from);
        $to   = $this->dateOnly($to);

        if ($from !== null && $date < $from) {
            return false;
        }

        if ($to !== null && $date > $to) {
            return false;
        }

        return true;
    }

    // Query-builder date columns sometimes arrive as "Y-m-d H:i:s". Compare date-only so
    // "today" is not treated as before midnight of the same day.
    private function dateOnly(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }
}
