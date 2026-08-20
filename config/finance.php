<?php

declare(strict_types=1);

return [

    'dng' => [
        /*
         * Kill-switch for automatic cancel-then-push replacement of a stale
         * live DNG collection whose targets no longer cover the current
         * payable. Default off until the behaviour has soaked in production.
         * See plans/260818-2139-dng-push-over-collection-replacement/.
         */
        'auto_replace_stale_collection' => env('DNG_AUTO_REPLACE_STALE_COLLECTION', false),
    ],

];
