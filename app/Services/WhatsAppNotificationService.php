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
     * Envía WhatsApp de bienvenida cuando son censados/registrados por primera vez en la PWA
     */
    public function sendGreetingNewCitizen($persona): bool
    {
        if (!$persona->numero_celular) {
            return false;
        }

        try {
            $n8nUrl = config('services.n8n.webhook_flow4_url') ?? 'https://n8n.soymetrix.com/webhook/enviar-mensaje';
            $metaToken = config('services.meta.token');
            $metaPhoneId = config('services.meta.phone_id');

            $payload = [
                'token' => $metaToken,
                'phone_number_id' => $metaPhoneId,
                'destinatario' => $persona->numero_celular,
                'tipo' => 'text',
                'mensaje' => "👋 ¡Hola, *{$persona->nombre}*!\n\n"
                    . "Has sido censado(a) y ya formas parte de nuestra red.\n"
                    . "A través de este canal recibirás invitaciones a eventos cerca de tu territorio según tus intereses. 🚀"
            ];

            $response = Http::post($n8nUrl, $payload);
            return $response->successful();

        } catch (\Exception $e) {
            Log::error("Fallo enviando bienvenida a ciudadano: " . $e->getMessage());
            return false;
        }
    }
}
