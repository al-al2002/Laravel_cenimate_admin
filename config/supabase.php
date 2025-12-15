<?php

return [
    'enabled' => env('SUPABASE_STORAGE_ENABLED', false),
    'url' => rtrim(env('SUPABASE_URL', ''), '/'),
    'anon_key' => env('SUPABASE_ANON_KEY'),
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'bucket' => env('SUPABASE_STORAGE_BUCKET', 'movie-posters'),
    'poster_prefix' => trim(env('SUPABASE_POSTER_PREFIX', 'movies'), '/'),
];
