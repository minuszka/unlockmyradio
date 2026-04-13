<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Test Credits
    |--------------------------------------------------------------------------
    |
    | During testing, newly created reseller accounts start with this many
    | credits. Set to 0 later when moving to paid top-up only mode.
    |
    */
    'test_default_credits' => (int) env('RESELLER_TEST_DEFAULT_CREDITS', 50),
];

