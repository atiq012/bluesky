<?php

// Tier CATALOGUE only. Which agency is on which tier lives in agents.tier.
// This file answers "what tiers exist", not "who is on them".
return [
    'list' => [
        'silver'   => ['label' => 'Silver',   'rank' => 1],
        'gold'     => ['label' => 'Gold',     'rank' => 2],
        'platinum' => ['label' => 'Platinum', 'rank' => 3],
    ],
    // Applied when agents.tier is null — keeps existing rows working with no backfill.
    'default' => 'silver',
];
