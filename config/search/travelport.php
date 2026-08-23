<?php

// Request-shaping knobs for TravelportSearchService::buildProviderPayload().
// Connection/credential config (urls, auth, cache) stays in config/services.php
// under travelport_v2 — this file is only the CatalogProductOfferingsRequest params.

return [
    // Which content sources to query. GDS = classic fares, NDC = airline-direct offers.
    'content_sources' => array_filter(explode(',', env('TRAVELPORT_SEARCH_CONTENT_SOURCES', 'GDS,NDC'))),

    'max_upsells'     => (int) env('TRAVELPORT_SEARCH_MAX_UPSELLS', 4),
    'offers_per_page' => (int) env('TRAVELPORT_SEARCH_OFFERS_PER_PAGE', 999),

    // CustomResponseModifiersAir.SearchRepresentation
    'search_representation' => env('TRAVELPORT_SEARCH_REPRESENTATION', 'Journey'),

    'default_cabin_class' => env('TRAVELPORT_SEARCH_DEFAULT_CABIN_CLASS', 'Economy'),

    // Http::timeout() for the search call, seconds.
    'request_timeout_seconds' => (int) env('TRAVELPORT_SEARCH_TIMEOUT_SECONDS', 60),

    // Ages sent per PassengerCriteria — Travelport needs an age to price CNN/KID/INF/INS correctly.
    // KID (2-<5) is not a real GDS PTC, so it's sent as CNN with age 3.
    'passenger_ages' => [
        'ADT' => (int) env('TRAVELPORT_SEARCH_AGE_ADT', 25),
        'CNN' => (int) env('TRAVELPORT_SEARCH_AGE_CNN', 8),
        'KID' => (int) env('TRAVELPORT_SEARCH_AGE_KID', 3),
        'INF' => (int) env('TRAVELPORT_SEARCH_AGE_INF', 1),
        'INS' => (int) env('TRAVELPORT_SEARCH_AGE_INS', 1),
    ],
];
