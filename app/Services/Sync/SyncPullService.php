<?php

namespace App\Services\Sync;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventStaffAssignment;
use App\Models\Persona;
use App\Models\QrCode;
use App\Models\User;
use Carbon\Carbon;

class SyncPullService
{
    private const SUPPORTED_TYPES = [
        'campanas',
        'eventos',
        'ciudadanos',
        'tickets',
        'usuarios',
        'staff_asignado',
        'asignaciones',
    ];

    public function pull(string $tipo, int $cuentaId, ?Carbon $since = null, ?int $eventoId = null): array
    {
        if (! in_array($tipo, self::SUPPORTED_TYPES, true)) {
            return [
                'success' => false,
                'errors' => ["Unsupported tipo '{$tipo}'"],
                'status' => 400,
            ];
        }

        $data = match ($tipo) {
            'campanas' => $this->pullCampanas($cuentaId, $since),
            'eventos' => $this->pullEventos($cuentaId, $since),
            'ciudadanos' => $this->pullCiudadanos($cuentaId, $since, $eventoId),
            'tickets' => $this->pullTickets($cuentaId, $since, $eventoId),
            'usuarios' => $this->pullUsuarios($cuentaId, $since),
            'staff_asignado' => $this->pullStaffAsignado($cuentaId, $since, $eventoId),
            'asignaciones' => $this->pullAsignaciones($cuentaId, $since, $eventoId),
        };

        return [
            'success' => true,
            'data' => $data,
            'meta' => [
                'server_time' => now()->toIso8601String(),
                'cuenta_id' => $cuentaId,
                'since_applied' => $since?->toIso8601String(),
            ],
        ];
    }

    private function pullCampanas(int $cuentaId, ?Carbon $since): array
    {
        return Campaign::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => (string) $campaign->id,
                'tenant_id' => $cuentaId,
                'nombre' => $campaign->name,
                'organizador' => $campaign->campaign_manager ?? $campaign->requesting_dependency ?? '',
                'fecha_inicio' => optional($campaign->start_date)->format('Y-m-d'),
                'fecha_fin' => optional($campaign->end_date)->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    private function pullEventos(int $cuentaId, ?Carbon $since): array
    {
        return Event::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Event $event) => [
                'id' => (string) $event->id,
                'tenant_id' => $cuentaId,
                'campana_id' => (string) ($event->campaign_id ?? ''),
                'nombre' => $event->detail,
                'lugar' => trim(collect([$event->street, $event->neighborhood, $event->municipality])->filter()->implode(', ')),
                'fecha' => $event->date,
                'hora' => $event->time,
                'descripcion' => $event->dynamic ?? '',
                'objetivo' => '',
                'protocolo_text' => '',
                'invitados_text' => '',
                'ubicacion_maps' => '',
                'asistentes_estimados' => $event->max_capacity ?? 0,
                'ninos_estimados' => 0,
                'duracion' => ($event->duration_hours ?? 2).' horas',
                'vestimenta' => 'Informal',
                'finalizado' => $event->ended_at ? 1 : 0,
                'plantilla_id' => null,
                'encuesta_random' => false,
            ])
            ->values()
            ->all();
    }

    private function pullCiudadanos(int $cuentaId, ?Carbon $since, ?int $eventoId): array
    {
        $query = EventAttendee::query()
            ->with(['persona.leader', 'persona.qrCodes'])
            ->when($eventoId, fn ($q) => $q->where('event_id', $eventoId))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since));

        return $query->get()->map(function (EventAttendee $attendee) use ($cuentaId) {
            $persona = $attendee->persona;
            if (! $persona) {
                return null;
            }

            $externalId = $this->personaExternalId($persona);
            $qrCode = $this->resolvePersonaQrCode($persona, $attendee);
            $leader = $persona->leader;

            return [
                'id' => $externalId.'__'.$attendee->event_id,
                'qr_code' => $qrCode,
                'tenant_id' => $cuentaId,
                'nombre' => $persona->nombre,
                'apellido_paterno' => $persona->apellido_paterno,
                'apellido_materno' => $persona->apellido_materno,
                'email' => '',
                'colonia' => $persona->colonia,
                'telefono' => $persona->numero_celular ?? $persona->numero_telefono ?? '',
                'curp' => $persona->curp,
                'documento_identidad' => $persona->cedula,
                'duplicate_phone_review' => 0,
                'lider_id' => $leader?->external_id ?? ($leader ? (string) $leader->id : null),
                'nombre_lider' => $leader ? trim("{$leader->nombre} {$leader->apellido_paterno}") : null,
                'promotor_id' => null,
                'nombre_promotor' => null,
                'mesa' => null,
                'consecutivo' => null,
                'ticket_delivered' => $attendee->checkin_at ? 1 : 0,
                'checkin_at' => optional($attendee->checkin_at)?->toIso8601String(),
                'sync_status' => 1,
            ];
        })->filter()->values()->all();
    }

    private function pullTickets(int $cuentaId, ?Carbon $since, ?int $eventoId): array
    {
        return EventAttendee::query()
            ->with('persona')
            ->when($eventoId, fn ($q) => $q->where('event_id', $eventoId))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get()
            ->map(function (EventAttendee $attendee) use ($cuentaId) {
                $persona = $attendee->persona;
                if (! $persona) {
                    return null;
                }

                $externalId = $this->personaExternalId($persona);

                return [
                    'id' => 'TKT_'.$attendee->event_id.'_'.$externalId,
                    'tenant_id' => $cuentaId,
                    'evento_id' => (string) $attendee->event_id,
                    'ciudadano_id' => $externalId.'__'.$attendee->event_id,
                    'estado_entrega' => $attendee->checkin_at ? 1 : 0,
                    'fecha_hora_recepcion' => optional($attendee->checkin_at)?->toIso8601String(),
                    'staff_escaneado_por_id' => $attendee->staff_user_id ?? 1,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function pullUsuarios(int $cuentaId, ?Carbon $since): array
    {
        return User::query()
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'tenant_id' => $cuentaId,
                'rol_id' => $this->mapHubRoleToMaster($user->role),
                'nombre' => $user->name,
                'email' => $user->email,
                'password' => $user->password,
                'activo' => $user->is_active ? 1 : 0,
            ])
            ->values()
            ->all();
    }

    private function pullStaffAsignado(int $cuentaId, ?Carbon $since, ?int $eventoId): array
    {
        return EventStaffAssignment::query()
            ->when($eventoId, fn ($q) => $q->where('event_id', $eventoId))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get()
            ->map(fn (EventStaffAssignment $assignment) => [
                'evento_id' => (string) $assignment->event_id,
                'usuario_id' => (string) $assignment->user_id,
                'es_entrega' => $assignment->es_entrega ? 1 : 0,
            ])
            ->values()
            ->all();
    }

    private function pullAsignaciones(int $cuentaId, ?Carbon $since, ?int $eventoId): array
    {
        return EventAttendee::query()
            ->with('persona')
            ->when($eventoId, fn ($q) => $q->where('event_id', $eventoId))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get()
            ->map(function (EventAttendee $attendee) use ($cuentaId) {
                $persona = $attendee->persona;
                if (! $persona) {
                    return null;
                }

                return [
                    'id' => (string) $attendee->id,
                    'tenant_id' => $cuentaId,
                    'event_id' => (string) $attendee->event_id,
                    'evento_id' => (string) $attendee->event_id,
                    'citizen_id' => $this->personaExternalId($persona),
                    'ciudadano_id' => $this->personaExternalId($persona),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function personaExternalId(Persona $persona): string
    {
        return $persona->external_id ?: (string) $persona->id;
    }

    private function resolvePersonaQrCode(Persona $persona, EventAttendee $attendee): ?string
    {
        if ($attendee->registration_qr_code) {
            return $attendee->registration_qr_code;
        }

        /** @var QrCode|null $qr */
        $qr = $persona->qrCodes
            ->sortByDesc('id')
            ->first(fn (QrCode $code) => $code->is_active && filled($code->code));

        return $qr?->code;
    }

    private function mapHubRoleToMaster(?string $role): int
    {
        return match ($role) {
            'admin' => 6,
            'staff' => 7,
            default => 8,
        };
    }
}
