<?php

return [
    'api_url' => env('EMAGIC_API_URL', 'https://api.facturacion.example.com'),
    'api_key' => env('EMAGIC_API_KEY'),
    'jwt_secret' => env('EMAGIC_JWT_SECRET'),
    'mode' => env('EMAGIC_MODE', 'test'),
    'repo_env' => env('EMAGIC_REPO_ENV'),
    'iamb' => env('EMAGIC_IAMB'),
    'denv_fe' => env('EMAGIC_DENV_FE'),
    'http_connect_timeout' => env('EMAGIC_HTTP_CONNECT_TIMEOUT', 5),
    'http_timeout' => env('EMAGIC_HTTP_TIMEOUT', 15),
];
