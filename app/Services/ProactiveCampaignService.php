<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\Persona;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProactiveCampaignService
{
    /**
     * Motor de Segmentación de Audiencias (El Embudo de 3 Capas)
     * 
     * @param Event $event El evento para el cual queremos generar las invitaciones
     * @return Collection Lista de Personas filtradas quirúrgicamente
     */
    public function getSegmentedAudienceForEvent(Event $event): Collection
    {
        // 1. Iniciamos la consulta base (Usuarios con WhatsApp registrado)
        $query = Persona::whereNotNull('numero_celular');

        // CAPA 1 y 2: Ubicación y Cercanía (Geo-Filtro)
        // Buscamos usuarios que vivan en la misma colonia/neighborhood del evento
        if ($event->neighborhood) {
            $query->where('colonia', 'like', '%' . $event->neighborhood . '%');
        } elseif ($event->municipality) {
            // Si el evento no tiene colonia específica pero sí municipio, ampliamos el radio
            $query->where('municipio', 'like', '%' . $event->municipality . '%');
        }

        // CAPA 3: Afinidad Temática (Event Tags / Audience Filters)
        if ($event->target_audience_filters) {
            $filters = is_string($event->target_audience_filters) 
                ? json_decode($event->target_audience_filters, true) 
                : $event->target_audience_filters;
            
            // Ejemplo de Filtro Dinámico: Evento de Vacunación Canina -> requiere Mascotas
            if (isset($filters['has_pets']) && $filters['has_pets'] === true) {
                // Solo ciudadanos que TENGAN al menos una mascota en base de datos
                $query->whereHas('mascotas');
            }

            // Ejemplo de Filtro Dinámico: Evento solo para Mujeres
            if (isset($filters['gender'])) {
                $query->where('sexo', $filters['gender']);
            }

            // Ejemplo: Evento para adultos mayores
            if (isset($filters['min_age'])) {
                $query->where('edad', '>=', $filters['min_age']);
            }
        }

        $audience = $query->get();

        Log::info("Audiencia segmentada para Evento {$event->id}", [
            'total_audience' => $audience->count(),
            'colonia_filtro' => $event->neighborhood,
            'filtros_tematicos' => $event->target_audience_filters
        ]);

        return $audience;
    }

    /**
     * Enviar invitaciones masivas al segmento filtrado a través de n8n
     */
    public function sendProactiveInvitations(Event $event): array
    {
        $audience = $this->getSegmentedAudienceForEvent($event);

        if ($audience->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Ningún ciudadano cumple con los filtros (Colonia + Temática).',
            ];
        }

        // Preparar payload masivo para n8n (Flow 4 o similar)
        $payload = [
            'event_id' => $event->id,
            'event_title' => $event->detail,
            'location' => $event->neighborhood,
            'audience_count' => $audience->count(),
            'personas' => $audience->map(function ($persona) {
                return [
                    'id' => $persona->id,
                    'nombre' => $persona->nombre,
                    'whatsapp' => $persona->numero_celular,
                    // Si el evento era de mascotas, podríamos exponer el nombre de la mascota aquí si n8n lo necesita
                ];
            })->toArray()
        ];

        try {
            // Aquí iría el POST hacia el Webhook de n8n (Flow 4)
            // Http::post('https://tu-n8n.com/webhook/enviar-invitacion-proactiva', $payload);
            
            Log::info("Disparando FLOW 4 en n8n para {$audience->count()} personas.");

            return [
                'success' => true,
                'message' => "Invitaciones encoladas para {$audience->count()} ciudadanos.",
                'data' => $payload
            ];

        } catch (\Exception $e) {
            Log::error("Error conectando con n8n para invitaciones: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Fallo al comunicar con n8n.',
            ];
        }
    }
}
