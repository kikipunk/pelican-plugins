<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Duration (in minutes)
    |--------------------------------------------------------------------------
    |
    | How long to cache the egg catalog data.
    | The catalog is fetched from https://pelican-eggs.github.io/pelican/
    |
    */
    'cache_duration' => env('EGG_LIBRARY_CACHE_DURATION', 60), // 1 hour
];
