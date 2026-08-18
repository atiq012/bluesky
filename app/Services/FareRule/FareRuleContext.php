<?php

namespace App\Services\FareRule;

// Readonly DTO of one bookable thing (a leg or a brand) being priced/matched against.
class FareRuleContext
{
    public readonly string $signature;

    public function __construct(
        public readonly string $carrier,
        public readonly ?string $supplier,
        public readonly string $origin,
        public readonly string $destination,
        public readonly ?string $cabin,
        public readonly ?string $bookingClass,
        public readonly string $onward,   // none | domestic | international — the actual connection, never 'any'
        public readonly string $scope,    // domestic | international | mixed — the actual trip scope, never 'any'
        public readonly ?string $flightDate = null,
        public readonly bool $hasAgent = false,
        public readonly ?int $agentId = null,
        public readonly ?string $agentTier = null,
        public readonly ?string $promoCode = null,
        public readonly string $feeEvent = 'issue',
        public readonly array $taxLines = [],
        public readonly bool $taxLinesExact = false,
        // Simulator only. Empty airline/supplier/cabin/agency on the test form means "don't filter",
        // not "this booking has no supplier" — search never sets this (a real booking has values).
        public readonly bool $unspecifiedMeansAny = false,
        // Fare components (§7.3/§7.4/§8) — the calculator's inputs travel with the same
        // "one bookable thing" DTO the matcher uses, so a caller builds one object, not two.
        public readonly float $base = 0.0,
        public readonly float $yq = 0.0,
        public readonly float $yr = 0.0,
        public readonly float $totalTaxes = 0.0,
        public readonly int $segments = 1,
        public readonly string $currency = 'BDT',
    ) {
        // §7.7-E — a search over ~1,000 brands typically has only 10-30 distinct signatures.
        $this->signature = implode('|', [
            $this->carrier,
            $this->origin,
            $this->destination,
            $this->cabin ?? '',
            $this->bookingClass ?? '',
            $this->scope,
            $this->onward,
        ]);
    }
}
