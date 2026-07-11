<?php

namespace App\Services\Sync;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Persona;
use App\Models\QrCode;
use App\Models\SyncBatchLog;
use App\Models\SyncDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncPushService
{
    private const MAX_BATCH_SIZE = 500;

    public function push(string $tipo, array $payload, int $cuentaId, ?SyncDevice $device = null, ?string $batchId = null): array
    {
        if (count($payload) > self::MAX_BATCH_SIZE) {
            return [
                'success' => false,
                'errors' => ['Batch exceeds maximum of '.self::MAX_BATCH_SIZE.' records'],
                'status' => 422,
            ];
        }

        $startedAt = microtime(true);
        $results = [];

        DB::transaction(function () use ($tipo, $payload, $cuentaId, &$results) {
            foreach ($payload as $item) {
                $results[] = match (strtoupper($tipo)) {
                    'CIUDADANOS' => $this->upsertCiudadano($item, $cuentaId),
                    'TICKETS' => $this->upsertTicket($item, $cuentaId),
                    default => [
                        'source_key' => $item['id'] ?? null,
                        'status' => 'rejected',
                        'error' => "Unsupported push tipo '{$tipo}'",
                    ],
                };
            }
        });

        $accepted = collect($results)->where('status', 'accepted')->count();
        $duplicates = collect($results)->where('status', 'duplicate')->count();
        $rejected = collect($results)->where('status', 'rejected')->count();

        SyncBatchLog::create([
            'cuenta_id' => $cuentaId,
            'sync_device_id' => $device?->id,
            'tipo' => strtoupper($tipo),
            'batch_id' => $batchId,
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'rejected' => $rejected,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return [
            'success' => true,
            'data' => [
                'accepted' => $accepted,
                'duplicates' => $duplicates,
                'rejected' => $rejected,
                'results' => $results,
            ],
            'meta' => [
                'server_time' => now()->toIso8601String(),
                'cuenta_id' => $cuentaId,
            ],
        ];
    }

    private function upsertCiudadano(array $item, int $cuentaId): array
    {
        $externalId = $this->resolveExternalPersonaId($item);
        if ($externalId === null) {
            return [
                'source_key' => null,
                'status' => 'rejected',
                'error' => 'Missing citizen id',
            ];
        }

        $persona = Persona::withoutGlobalScopes()
            ->where('cuenta_id', $cuentaId)
            ->where('external_id', $externalId)
            ->first();

        $telefono = $item['telefono'] ?? $item['numero_celular'] ?? null;
        $qrCode = $item['qr_code'] ?? $item['qr'] ?? null;

        if ($persona) {
            $persona->fill([
                'nombre' => $item['nombre'] ?? $persona->nombre,
                'apellido_paterno' => $item['apellido_paterno'] ?? $persona->apellido_paterno,
                'apellido_materno' => $item['apellido_materno'] ?? $persona->apellido_materno,
                'colonia' => $item['colonia'] ?? $persona->colonia,
                'numero_celular' => $telefono ?? $persona->numero_celular,
                'numero_telefono' => $telefono ?? $persona->numero_telefono,
                'curp' => $item['curp'] ?? $persona->curp,
            ])->save();

            if ($qrCode) {
                $this->ensurePersonaQrCode($persona, $qrCode, $cuentaId);
            }

            return [
                'source_key' => $externalId,
                'status' => 'duplicate',
                'hub_id' => $persona->id,
            ];
        }

        $persona = Persona::withoutGlobalScopes()->create([
            'cuenta_id' => $cuentaId,
            'external_id' => $externalId,
            'cedula' => $item['documento_identidad'] ?? $item['curp'] ?? 'SYNC-'.Str::upper(Str::substr($externalId, 0, 12)),
            'curp' => $item['curp'] ?? null,
            'nombre' => $item['nombre'] ?? 'Sin nombre',
            'apellido_paterno' => $item['apellido_paterno'] ?? '',
            'apellido_materno' => $item['apellido_materno'] ?? '',
            'edad' => 0,
            'sexo' => 'O',
            'calle' => '',
            'numero_exterior' => '',
            'colonia' => $item['colonia'] ?? '',
            'codigo_postal' => '',
            'municipio' => '',
            'estado' => '',
            'numero_celular' => $telefono,
            'numero_telefono' => $telefono,
            'universe_type' => 'U1',
        ]);

        if ($qrCode) {
            $this->ensurePersonaQrCode($persona, $qrCode, $cuentaId);
        }

        return [
            'source_key' => $externalId,
            'status' => 'accepted',
            'hub_id' => $persona->id,
        ];
    }

    private function upsertTicket(array $item, int $cuentaId): array
    {
        $eventId = (int) ($item['evento_id'] ?? $item['event_id'] ?? 0);
        $externalPersonaId = $this->resolveExternalPersonaId($item, true);

        if ($eventId <= 0 || $externalPersonaId === null) {
            return [
                'source_key' => $item['id'] ?? null,
                'status' => 'rejected',
                'error' => 'Missing evento_id or ciudadano_id',
            ];
        }

        $eventExists = Event::withoutGlobalScopes()
            ->where('cuenta_id', $cuentaId)
            ->whereKey($eventId)
            ->exists();

        if (! $eventExists) {
            return [
                'source_key' => $item['id'] ?? null,
                'status' => 'rejected',
                'error' => 'Event not found for tenant',
            ];
        }

        $persona = Persona::withoutGlobalScopes()
            ->where('cuenta_id', $cuentaId)
            ->where('external_id', $externalPersonaId)
            ->first();

        if (! $persona) {
            $persona = Persona::withoutGlobalScopes()->create([
                'cuenta_id' => $cuentaId,
                'external_id' => $externalPersonaId,
                'cedula' => 'SYNC-'.Str::upper(Str::substr($externalPersonaId, 0, 12)),
                'nombre' => 'Walk-in',
                'apellido_paterno' => '',
                'apellido_materno' => '',
                'edad' => 0,
                'sexo' => 'O',
                'calle' => '',
                'numero_exterior' => '',
                'colonia' => '',
                'codigo_postal' => '',
                'municipio' => '',
                'estado' => '',
                'universe_type' => 'U1',
            ]);
        }

        $checkinAt = $this->resolveCheckinAt($item);
        $sourceKey = $this->buildSourceKey(
            $cuentaId,
            $eventId,
            $externalPersonaId,
            $checkinAt ?? now(),
            'entry'
        );

        $existing = EventAttendee::withoutGlobalScopes()
            ->where('cuenta_id', $cuentaId)
            ->where('source_key', $sourceKey)
            ->first();

        if ($existing) {
            return [
                'source_key' => $sourceKey,
                'status' => 'duplicate',
                'hub_id' => $existing->id,
            ];
        }

        $attendee = EventAttendee::withoutGlobalScopes()->updateOrCreate(
            [
                'cuenta_id' => $cuentaId,
                'event_id' => $eventId,
                'persona_id' => $persona->id,
            ],
            [
                'source_key' => $sourceKey,
                'checkin_at' => $checkinAt,
                'attendance_status' => $checkinAt ? 'entered' : 'registered',
                'staff_user_id' => isset($item['staff_escaneado_por_id']) ? (int) $item['staff_escaneado_por_id'] : null,
                'registered_at' => now(),
            ]
        );

        $wasRecentlyCreated = $attendee->wasRecentlyCreated;

        return [
            'source_key' => $sourceKey,
            'status' => $wasRecentlyCreated ? 'accepted' : 'duplicate',
            'hub_id' => $attendee->id,
        ];
    }

    private function resolveExternalPersonaId(array $item, bool $allowComposite = false): ?string
    {
        $raw = $item['ciudadano_id'] ?? $item['citizen_id'] ?? $item['id'] ?? null;

        if ($raw === null) {
            return null;
        }

        $raw = (string) $raw;

        if ($allowComposite && str_contains($raw, '__')) {
            return explode('__', $raw, 2)[0];
        }

        if (! $allowComposite && str_contains($raw, '__')) {
            return explode('__', $raw, 2)[0];
        }

        return $raw;
    }

    private function resolveCheckinAt(array $item): ?Carbon
    {
        $delivered = $item['estado_entrega'] ?? 0;

        if ((int) $delivered !== 1 && empty($item['fecha_hora_recepcion'])) {
            return null;
        }

        if (! empty($item['fecha_hora_recepcion'])) {
            return Carbon::parse($item['fecha_hora_recepcion']);
        }

        return now();
    }

    public function buildSourceKey(int $cuentaId, int $eventId, string $personaExternalId, Carbon $checkinAt, string $scanType = 'entry'): string
    {
        return implode(':', [
            $cuentaId,
            $eventId,
            $personaExternalId,
            $checkinAt->timezone(config('app.timezone'))->format('Y-m-d'),
            $scanType,
        ]);
    }

    private function ensurePersonaQrCode(Persona $persona, string $code, int $cuentaId): void
    {
        QrCode::withoutGlobalScopes()->updateOrCreate(
            [
                'cuenta_id' => $cuentaId,
                'persona_id' => $persona->id,
                'code' => $code,
            ],
            [
                'type' => 'militant',
                'is_active' => true,
            ]
        );
    }
}
