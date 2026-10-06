<?php

return [
    /*
    | Las llaves nunca deben ir en controladores, vistas ni repositorio.
    | Se colocan únicamente en el archivo .env del servidor.
    */
    'enabled' => env('CULQI_ENABLED', false),
    'environment' => env('CULQI_ENVIRONMENT', 'test'),
    'public_key' => env('CULQI_PUBLIC_KEY'),
    'secret_key' => env('CULQI_SECRET_KEY'),
    'webhook_secret' => env('CULQI_WEBHOOK_SECRET'),
    'api_url' => env('CULQI_API_URL', 'https://api.culqi.com/v2'),
];
