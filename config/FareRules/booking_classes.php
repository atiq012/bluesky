<?php

// RBD (booking class) catalogue — drives the booking-class picker in the rule editor.
// The old system hardcoded this A-Z array inside create.vue AND again inside edit.vue,
// and the selected values were never read by the matcher at all.
return [
    // Suggestion list shown in the picker.
    'list' => [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
    ],

    // Real RBDs are airline-specific and not always a single letter — Travelport
    // sends booking codes like 'Y1' or 'QW'. The picker must accept those too,
    // otherwise a rule simply cannot be written for them.
    'allow_custom' => true,

    // Validates anything typed in: 1-2 chars, letter first.
    'pattern' => '/^[A-Z][A-Z0-9]?$/',
];
