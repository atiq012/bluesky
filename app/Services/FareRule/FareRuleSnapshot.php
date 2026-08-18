<?php

namespace App\Services\FareRule;

// Plain-array rule set + prebuilt lookup indexes (§7.7-C). Never Eloquent (invariant §16.1-2) —
// this is the shape that gets cached in Phase 4, so it must stay trivially serializable.
class FareRuleSnapshot
{
    // Candidate narrowing is a pure function of (carrier, origin, destination) against this
    // snapshot's own immutable indexes — many brands of the same leg share the same triple, so
    // this cache turns "recompute the intersection per brand" into "recompute per distinct leg".
    // Safe to keep on the snapshot instance itself: it holds no request-specific data.
    private array $legCandidateCache = [];

    public function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function rule(int $id): ?array
    {
        return $this->data['rules'][$id] ?? null;
    }

    public function allRules(): array
    {
        return $this->data['rules'];
    }

    public function count(): int
    {
        return count($this->data['rules']);
    }

    // (by_airline[carrier] ∪ airline_any) ∩ (by_origin[o] ∪ origin_any ∪ origin_wildcard)
    //   ∩ (by_dest[d] ∪ dest_any ∪ dest_wildcard) — typically cuts 200 rules to 10-30 (§7.7-C).
    // Safe by construction: a rule is only ever excluded here for a reason the matcher would
    // also reject it for, so narrowing can never drop a true match (proven by
    // index_narrowing_returns_same_winner_as_full_scan).
    public function candidatesForLeg(string $carrier, string $origin, string $destination): array
    {
        $key = "{$carrier}|{$origin}|{$destination}";
        if (isset($this->legCandidateCache[$key])) {
            return $this->legCandidateCache[$key];
        }

        $idx = $this->data['indexes'];

        $airlineIds = array_merge($idx['by_airline'][$carrier] ?? [], $idx['airline_any']);
        $originIds  = array_merge($idx['by_origin'][$origin] ?? [], $idx['origin_any'], $idx['origin_wildcard']);
        $destIds    = array_merge($idx['by_dest'][$destination] ?? [], $idx['dest_any'], $idx['dest_wildcard']);

        $ids = array_intersect($airlineIds, $originIds, $destIds);

        return $this->legCandidateCache[$key] = array_values(array_intersect_key($this->data['rules'], array_flip($ids)));
    }

    public function usesCabinOrBookingClass(int $ruleId): bool
    {
        return in_array($ruleId, $this->data['indexes']['uses_cabin'], true)
            || in_array($ruleId, $this->data['indexes']['uses_rbd'], true);
    }
}
