<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Metrix Central API (JWT validation on protected Hub routes)
    |--------------------------------------------------------------------------
    |
    | Login happens on Central directly from the Events Hub frontend.
    | The Hub backend only calls Central /auth/me to validate bearer tokens.
    |
    */
    'api_url' => rtrim((string) env('CENTRAL_API_URL', ''), '/'),
];
