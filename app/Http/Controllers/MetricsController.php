<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSlot;
use App\Models\Appointment;
use App\Models\EventAttendee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Obtener métricas detalladas de un evento específico
     */
    public function eventMetrics(int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);

        // 1. Métricas de Citas y Tiempos
        $appointmentStats = Appointment::where('event_id', $eventId)
            ->selectRaw('
                COUNT(*) as total_appointments,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_appointments,
                AVG(service_duration_minutes) as avg_duration_minutes,
                MIN(service_duration_minutes) as min_duration_minutes,
                MAX(service_duration_minutes) as max_duration_minutes
            ')
            ->first();

        // 2. Eficiencia por Ubicación (Mesa/Desk)
        $locationStats = Appointment::where('event_id', $eventId)
            ->where('status', 'completed')
            ->whereNotNull('assigned_location')
            ->groupBy('assigned_location')
            ->selectRaw('
                assigned_location,
                COUNT(*) as total_served,
                AVG(service_duration_minutes) as avg_location_duration
            ')
            ->get();

        // 3. Métricas de Asistencia General (Loyalty)
        $attendanceStats = EventAttendee::where('event_id', $eventId)
            ->selectRaw('
                COUNT(*) as registered_attendees,
                SUM(CASE WHEN checkin_at IS NOT NULL THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN checkout_at IS NOT NULL THEN 1 ELSE 0 END) as completed_attendance_count
            ')
            ->first();

        // 4. Distribución por Universos
        $universeStats = EventAttendee::where('event_id', $eventId)
            ->join('personas', 'event_attendees.persona_id', '=', 'personas.id')
            ->groupBy('personas.universe_type')
            ->selectRaw('personas.universe_type, COUNT(*) as count')
            ->get();

        return response()->json([
            'success' => true,
            'event' => [
                'id' => $event->id,
                'name' => $event->detail,
            ],
            'appointments' => [
                'total' => $appointmentStats->total_appointments,
                'completed' => $appointmentStats->completed_appointments,
                'avg_duration' => round((float)$appointmentStats->avg_duration_minutes, 2),
                'min_duration' => $appointmentStats->min_duration_minutes,
                'max_duration' => $appointmentStats->max_duration_minutes,
                'completion_rate' => $appointmentStats->total_appointments > 0 
                    ? round(($appointmentStats->completed_appointments / $appointmentStats->total_appointments) * 100, 2) 
                    : 0,
            ],
            'locations' => $locationStats,
            'attendance' => [
                'total_registered' => $attendanceStats->registered_attendees,
                'here_now' => $attendanceStats->present_count - $attendanceStats->completed_attendance_count,
                'total_completed' => $attendanceStats->completed_attendance_count,
            ],
            'universes' => $universeStats
        ]);
    }

    /**
     * Dashboard General (Resumen de todos los eventos)
     */
    public function globalSummary(): JsonResponse
    {
        $globalStats = Event::selectRaw('
                COUNT(*) as total_events,
                SUM(max_capacity) as total_capacity,
                SUM(checked_in_count) as total_attendance
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $globalStats
        ]);
    }

    /**
     * MÉTRICAS EN TIEMPO REAL (Pilar 5.1)
     * Proporciona estado en vivo de asistencia, capacidad y flujo por hora.
     */
    public function realtimeEvent(string $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);
        
        return response()->json([
            'success' => true,
            'event_id' => $event->id,
            'timestamp' => now()->toISOString(),
            'live' => [
                'currently_inside' => EventAttendee::where('event_id', $eventId)
                    ->whereNotNull('checkin_at')
                    ->whereNull('checkout_at')
                    ->count(),
                'total_entered' => EventAttendee::where('event_id', $eventId)
                    ->whereNotNull('checkin_at')->count(),
                'total_exited' => EventAttendee::where('event_id', $eventId)
                    ->whereNotNull('checkout_at')->count(),
                'avg_duration_minutes' => round(
                    EventAttendee::where('event_id', $eventId)
                        ->whereNotNull('checkin_at')
                        ->whereNotNull('checkout_at')
                        ->selectRaw('AVG(EXTRACT(EPOCH FROM (checkout_at - checkin_at)) / 60) as avg_duration')
                        ->first()->avg_duration ?? 0, 2
                ),
                'capacity_percentage' => round(
                    (EventAttendee::where('event_id', $eventId)->count() / max($event->max_capacity, 1)) * 100, 1
                ),
            ],
            'demographics' => [
                'by_gender' => EventAttendee::where('event_id', $eventId)
                    ->join('personas', 'event_attendees.persona_id', '=', 'personas.id')
                    ->selectRaw("personas.sexo, COUNT(*) as total")
                    ->groupBy('personas.sexo')->pluck('total', 'sexo'),
                'by_age_range' => $this->getAgeDistribution($eventId),
            ],
            'hourly_flow' => $this->getHourlyFlow($eventId),
        ]);
    }

    private function getAgeDistribution(int $eventId): array
    {
        return DB::table('event_attendees')
            ->join('personas', 'event_attendees.persona_id', '=', 'personas.id')
            ->where('event_attendees.event_id', $eventId)
            ->selectRaw("
                CASE 
                    WHEN personas.edad < 18 THEN 'Menores'
                    WHEN personas.edad BETWEEN 18 AND 30 THEN '18-30'
                    WHEN personas.edad BETWEEN 31 AND 50 THEN '31-50'
                    ELSE '50+'
                END as range,
                COUNT(*) as count
            ")
            ->groupBy('range')
            ->pluck('count', 'range')
            ->toArray();
    }

    private function getHourlyFlow(int $eventId): array
    {
        return DB::table('event_attendees')
            ->where('event_id', $eventId)
            ->whereNotNull('checkin_at')
            ->selectRaw("TO_CHAR(checkin_at, 'HH24:00') as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
    }

    /**
     * Reporte Nocturno (Pilar 5.2 / FLOW 13)
     * Resumen de actividad del día para envío administrativo a las 23:00.
     */
    public function nightlyReport(): JsonResponse
    {
        $today = now()->format('Y-m-d');
        
        $todayEvents = Event::where('date', $today)->count();
        $newRegistrations = EventAttendee::whereDate('created_at', $today)->count();
        $totalCheckins = EventAttendee::whereDate('checkin_at', $today)->count();
        $completedEvents = Event::where('date', $today)->where('status', 'completed')->count();
        
        // Sumar puntos distribuidos hoy
        $pointsToday = DB::table('bonus_point_histories')
            ->whereDate('created_at', $today)
            ->sum('points_awarded');

        return response()->json([
            'success' => true,
            'date' => $today,
            'summary' => [
                'events_today' => $todayEvents,
                'completed_events' => $completedEvents,
                'new_registrations' => $newRegistrations,
                'total_checkins' => $totalCheckins,
                'points_distributed' => (int)$pointsToday,
            ],
            'top_events' => Event::where('date', $today)
                ->orderBy('checked_in_count', 'desc')
                ->limit(3)
                ->get(['id', 'detail', 'checked_in_count', 'max_capacity'])
        ]);
    }
}
