<?php

namespace App\Services\DynamicRule;

class DynamicRuleMatchContext
{
    public function __construct(
        public readonly string $travelDate,
        public readonly ?int $agencyId,
        public readonly ?string $agencyName,
        public readonly ?string $agencyType,
        public readonly ?string $agencyGroup,
        public readonly ?string $api,
        public readonly string $departureCode,
        public readonly string $arrivalCode,
        public readonly array $airlineCodes,
        public readonly array $airlineNames,
        public readonly string $flightType,
        public readonly string $wayType,
        public readonly string $cabinClass,
        public readonly bool $isRoundTrip,
    ) {}
}
