<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Direct Reveal Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, web search shows the radio code immediately and checkout
    | is disabled. Useful for data/label testing before Stripe onboarding.
    |
    */
    'direct_reveal' => (bool) env('UNLOCK_DIRECT_REVEAL', true),
];

