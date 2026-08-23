<?php

// Supplier / GDS catalogue. Only Travelport is live today, but this list is
// expected to grow — treat it as a large dataset, not a fixed triple.
return [
    'travelport_1g' => ['label' => 'Travelport 1G', 'aliases' => ['Travelport', 'Travelport UAPI', '1G'], 'active' => true],
    'amadeus'       => ['label' => 'Amadeus',       'aliases' => ['1A'],  'active' => false],
    'airline_ndc'   => ['label' => 'Airline NDC',   'aliases' => ['NDC'], 'active' => false],
];
