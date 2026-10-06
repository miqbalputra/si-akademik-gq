<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV', 'production') === 'production'),
    'hsts_max_age' => 31_536_000,
];
