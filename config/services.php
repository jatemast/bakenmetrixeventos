<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'n8n' => [
        'notification_webhook_url' => env('N8N_NOTIFICATION_WEBHOOK_URL'),
        'portal_otp_webhook' => env('N8N_PORTAL_OTP_WEBHOOK'),
        'webhook_flow4_url' => env('N8N_FLOW4_WEBHOOK_URL', 'https://n8n.soymetrix.com/webhook/enviar-invitacion'),
        'webhook_flow7_broadcast_url' => env('N8N_FLOW7_BROADCAST_URL', 'https://n8n.soymetrix.com/webhook/broadcast-invitaciones'),
        'webhook_flow6_registro_url' => env('N8N_FLOW6_REGISTRO_URL', 'https://n8n.soymetrix.com/webhook/registro-crm-full'),
    ],

    'meta' => [
        'token' => env('META_WHATSAPP_TOKEN'),
        'phone_id' => env('META_WHATSAPP_PHONE_ID'),
    ],
];
