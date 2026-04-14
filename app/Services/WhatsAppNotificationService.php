<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    /**
     * Envía la confirmación de cita con el QR/Token por WhatsApp
     */
    public function sendAppointmentConfirmation(Appointment $appointment): bool
    {
        $persona = $appointment->persona;
        $event = $appointment->event;
        $slot = $appointment->slot;

        if (!$persona->numero_celular) {
            Log::warning("No se pudo enviar WhatsApp a Persona ID {$persona->id}: No tiene número registrado (numero_celular).");
            return false;
        }

        $message = "¡Hola {$persona->nombre}! 🐾\n\n" .
                   "Tu cita para el evento *{$event->detail}* ha sido confirmada.\n" .
                   "📅 *Fecha:* {$event->date}\n" .
                   "⏰ *Horario:* {$slot->start_time->format('h:i A')} - {$slot->end_time->format('h:i A')}\n" .
                   "📍 *Ubicación:* {$event->location}\n\n" .
                   "Presenta este código al llegar: *{$appointment->qr_code_token}*\n\n" .
                   "¡Te esperamos!";

        try {
            // Disparar Webhook de n8n (FLOW 4: Enviar Mensaje WhatsApp)
            $n8nUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-mensaje';
            $metaToken = config('services.meta.token');
            $metaPhoneId = config('services.meta.phone_id');

            if (!$metaToken || !$metaPhoneId) {
                Log::warning("No se ha configurado Token o Phone_ID de Meta en services.php");
            }

            // JSON estricto para nuestro FLOW 4 recién actualizado
            $payload = [
                'token' => $metaToken,
                'phone_number_id' => $metaPhoneId,
                'destinatario' => $persona->numero_celular ?? $persona->whatsapp,
                'tipo' => 'template',
                'template_name' => 'confirmacion_cita_codigo', // El nombre de la plantilla aprobada en Meta
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                    // Usamos un generador de QR público para que Meta pueda verlo desde localhost
                                    'link' => "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data={$appointment->qr_code_token}"
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'parameter_name' => 'nombre_ciudadano', 'text' => $persona->nombre],
                            ['type' => 'text', 'parameter_name' => 'nombre_evento', 'text' => $event->detail],
                            ['type' => 'text', 'parameter_name' => 'fecha_evento', 'text' => $event->date],
                            ['type' => 'text', 'parameter_name' => 'hora_evento', 'text' => $slot->start_time->format('h:i A')],
                            ['type' => 'text', 'parameter_name' => 'folio_digital', 'text' => $appointment->qr_code_token]
                        ]
                    ]
                ]
            ];

            Log::info("Disparando webhook FLOW 4 para WhatsApp (Template) a {$payload['destinatario']}");
            
            $response = Http::post($n8nUrl, $payload);

            if ($response->successful() && isset($event->checkout_code)) {
                // Enviar Segundo Mensaje: QR de Salida
                $payloadExit = [
                    'token' => $metaToken,
                    'phone_number_id' => $metaPhoneId,
                    'destinatario' => $persona->numero_celular,
                    'tipo' => 'text',
                    'mensaje' => "📤 *Tu código QR de Salida*\n"
                        . "Presenta este código al terminar el evento para validar tus puntos acumulados:\n\n"
                        . "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data={$event->checkout_code}"
                ];
                Http::post($n8nUrl, $payloadExit);
            }

            if ($response->failed()) {
                Log::error("Fallo al enviar a n8n FLOW 4: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error de conexión con n8n FLOW 4: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Entry (QR2) and Exit (QR3) codes to a registered attendee
     */
    public function sendEventQrs($persona, $event): bool
    {
        [$metaToken, $metaPhoneId] = $this->getMetaCredentials();
        $n8nUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-mensaje';

        if (!$persona->numero_celular) return false;

        $checkinQr = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . ($event->checkin_code);
        $checkoutQr = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . ($event->checkout_code);

        $message = "✅ *¡Registro Confirmado!*\n\n"
                 . "Hola {$persona->nombre}, ya estás registrado(a) para el evento:\n"
                 . "📍 *{$event->detail}*\n"
                 . "📅 *Fecha:* {$event->date} a las {$event->time}\n\n"
                 . "--------------------------\n"
                 . "🎟️ *TU CÓDIGO DE ENTRADA (SCAN ENTRY)*\n"
                 . "{$checkinQr}\n\n"
                 . "📤 *TU CÓDIGO DE SALIDA (SCAN EXIT)*\n"
                 . "{$checkoutQr}\n\n"
                 . "¡Muestra estos códigos al personal del evento!";

        $payload = [
            'token' => $metaToken,
            'phone_number_id' => $metaPhoneId,
            'destinatario' => $persona->numero_celular,
            'tipo' => 'text',
            'mensaje' => $message,
        ];

        try {
            $response = Http::timeout(10)->post($n8nUrl, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error sending event QRs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send specialized invitation to a Leader (U3)
     * Includes personal entry/exit and their unique Guest invitation QR/Link
     */
    public function sendLeaderEventInvitation($persona, $event, string $leaderQrCode): bool
    {
        [$metaToken, $metaPhoneId] = $this->getMetaCredentials();
        $n8nUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-mensaje';

        if (!$persona->numero_celular) return false;

        $checkinQr = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . ($event->checkin_code);
        $leaderInvitationUrl = url("/invitation/{$leaderQrCode}");

        $message = "👑 *¡CONVOCATORIA PARA LÍDERES!*\n\n"
                 . "Hola {$persona->nombre}, eres clave para el éxito del evento:\n"
                 . "📍 *{$event->detail}*\n"
                 . "📅 *Fecha:* {$event->date} - {$event->time}\n\n"
                 . "--------------------------\n"
                 . "🎟️ *TU ACCESO PERSONAL (STAFF)*\n"
                 . "{$checkinQr}\n\n"
                 . "🚀 *TU ENLACE ÚNICO DE INVITACIÓN*\n"
                 . "Usa este enlace para registrar a tu grupo y que cuente para tus metas:\n"
                 . "🔗 {$leaderInvitationUrl}\n\n"
                 . "¡Vamos por todo!";

        $payload = [
            'token' => $metaToken,
            'phone_number_id' => $metaPhoneId,
            'destinatario' => $persona->numero_celular,
            'tipo' => 'text',
            'mensaje' => $message,
        ];

        try {
            $response = Http::timeout(10)->post($n8nUrl, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error sending leader invitation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send welcome message to a newly registered user
     */
    public function sendWelcomeMessage($persona): bool
    {
        [$metaToken, $metaPhoneId] = $this->getMetaCredentials();
        // New Orchestrator Webhook
        $n8nUrl = config('services.n8n.webhook_orchestrator_url') ?? 'https://n8n.soymetrix.com/webhook/registro-exitoso-orquestador';
        
        $payload = [
            'action' => 'WELCOME_NEW_USER',
            'persona' => [
                'id' => $persona->id,
                'nombre' => $persona->nombre,
                'apellido' => $persona->apellido_paterno,
                'whatsapp' => $persona->numero_celular,
                'universe_type' => $persona->universe_type,
                'sub_type' => $persona->sub_type,
                'tenant_id' => $persona->cuenta_id ?? $persona->tenant_id,
            ],
            'meta' => [
                'token' => $metaToken,
                'phone_id' => $metaPhoneId
            ]
        ];

        try {
            Log::info("Triggering n8n ORCHESTRATOR for welcome: {$persona->numero_celular}");
            $response = Http::timeout(10)->post($n8nUrl, $payload);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Error triggering orchestrator: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Meta credentials with multiple fallback mechanisms
     */
    private function getMetaCredentials(): array
    {
        $token = config('services.meta.token') ?? env('META_WHATSAPP_TOKEN');
        $phoneId = config('services.meta.phone_id') ?? env('META_WHATSAPP_PHONE_ID');

        if (!$token || !$phoneId) {
            Log::warning('Meta WhatsApp credentials missing in both config and env.');
        }

        return [$token, $phoneId];
    }

    /**
     * Send general greeting to a new citizen (Censo)
     */
    public function sendGreetingNewCitizen($persona): bool
    {
        [$metaToken, $metaPhoneId] = $this->getMetaCredentials();
        $n8nUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-invitacion';
        
        $destinatario = $persona->numero_celular;
        $nombre = $persona->nombre;

        $payload = [
            'token' => $metaToken,
            'phone_number_id' => $metaPhoneId,
            'destinatario' => $destinatario,
            'tipo' => 'text',
            'mensaje' => "👋 ¡Hola, *{$nombre}*!\n\n"
                . "Has sido censado(a) y ya formas parte de nuestra red.\n"
                . "A través de este canal recibirás invitaciones a eventos cerca de tu territorio según tus intereses. 🚀",
        ];

        try {
            Log::info("Sending greeting to {$destinatario} via n8n", ['url' => $n8nUrl, 'phone_id' => $metaPhoneId]);
            $response = Http::timeout(10)->post($n8nUrl, $payload);
            
            if ($response->failed()) {
                Log::error("n8n greeting webhooks failed: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Error triggering greeting webhook: " . $e->getMessage());
            return false;
        }
    }
}
