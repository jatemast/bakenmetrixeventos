<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Persona;
use App\Models\QrCode;
use App\Models\BonusPointHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Get campaign statistics
     */
    public function campaignStats($campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);

            $events = Event::where('campaign_id', $campaignId)->get();
            $eventIds = $events->pluck('id');

            $totalEvents = $events->count();
            $completedEvents = $events->where('status', 'completed')->count();

            $totalRegistrations = EventAttendee::whereIn('event_id', $eventIds)->count();
            $totalAttendees = EventAttendee::whereIn('event_id', $eventIds)
                ->whereNotNull('entry_timestamp')
                ->count();

            $totalPointsAwarded = BonusPointHistory::whereIn('event_id', $eventIds)
                ->where('points_awarded', '>', 0)
                ->sum('points_awarded');

            $qrScans = QrCode::whereIn('event_id', $eventIds)
                ->sum('scan_count');

            // Universe distribution
            $universeStats = EventAttendee::whereIn('event_id', $eventIds)
                ->join('personas', 'event_attendees.persona_id', '=', 'personas.id')
                ->select('personas.universe_type', DB::raw('count(*) as count'))
                ->groupBy('personas.universe_type')
                ->get()
                ->pluck('count', 'universe_type');

            // Attendance rate per event
            $eventStats = $events->map(function($event) {
                $registered = EventAttendee::where('event_id', $event->id)->count();
                $attended = EventAttendee::where('event_id', $event->id)
                    ->whereNotNull('entry_timestamp')
                    ->count();

                return [
                    'event_id' => $event->id,
                    'event_name' => $event->detail,
                    'event_date' => $event->date,
                    'registered' => $registered,
                    'attended' => $attended,
                    'attendance_rate' => $registered > 0 ? round(($attended / $registered) * 100, 2) : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'campaign' => [
                        'id' => $campaign->id,
                        'title' => $campaign->title,
                        'start_date' => $campaign->start_date,
                        'end_date' => $campaign->end_date,
                    ],
                    'overview' => [
                        'total_events' => $totalEvents,
                        'completed_events' => $completedEvents,
                        'total_registrations' => $totalRegistrations,
                        'total_attendees' => $totalAttendees,
                        'total_points_awarded' => $totalPointsAwarded,
                        'total_qr_scans' => $qrScans,
                    ],
                    'universe_distribution' => $universeStats,
                    'events' => $eventStats,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Campaign stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de campaña.'
            ], 500);
        }
    }

    /**
     * Get event attendance report
     */
    public function eventAttendance($eventId): JsonResponse
    {
        try {
            $event = Event::with('campaign')->findOrFail($eventId);

            $attendees = EventAttendee::where('event_id', $eventId)
                ->with('persona')
                ->get();

            $totalRegistered = $attendees->count();
            $totalEntered = $attendees->whereNotNull('entry_timestamp')->count();
            $totalCompleted = $attendees->where('attendance_status', 'completed')->count();

            // Calculate average duration
            $completedAttendees = $attendees->filter(function($a) {
                return $a->entry_timestamp && $a->exit_timestamp;
            });

            $avgDuration = 0;
            if ($completedAttendees->count() > 0) {
                $totalMinutes = $completedAttendees->sum(function($a) {
                    $entry = \Carbon\Carbon::parse($a->entry_timestamp);
                    $exit = \Carbon\Carbon::parse($a->exit_timestamp);
                    return $exit->diffInMinutes($entry);
                });
                $avgDuration = round($totalMinutes / $completedAttendees->count(), 2);
            }

            // Attendance by universe
            $byUniverse = $attendees->groupBy(function($a) {
                return $a->persona->universe_type ?? 'Unknown';
            })->map(function($group) {
                return [
                    'total' => $group->count(),
                    'entered' => $group->whereNotNull('entry_timestamp')->count(),
                    'completed' => $group->where('attendance_status', 'completed')->count(),
                ];
            });

            // Attendance timeline (entries per hour)
            $timeline = $attendees->filter(function($a) {
                return $a->entry_timestamp;
            })->groupBy(function($a) {
                return \Carbon\Carbon::parse($a->entry_timestamp)->format('H:00');
            })->map(function($group) {
                return $group->count();
            })->sortKeys();

            return response()->json([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->detail,
                        'date' => $event->date,
                        'location' => $event->location,
                        'campaign' => $event->campaign->title ?? null,
                    ],
                    'summary' => [
                        'total_registered' => $totalRegistered,
                        'total_entered' => $totalEntered,
                        'total_completed' => $totalCompleted,
                        'attendance_rate' => $totalRegistered > 0 ? round(($totalEntered / $totalRegistered) * 100, 2) : 0,
                        'completion_rate' => $totalEntered > 0 ? round(($totalCompleted / $totalEntered) * 100, 2) : 0,
                        'average_duration_minutes' => $avgDuration,
                    ],
                    'by_universe' => $byUniverse,
                    'timeline' => $timeline,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Event attendance report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reporte de asistencia.'
            ], 500);
        }
    }

    /**
     * Get universe distribution across all events
     */
    public function universeDistribution(): JsonResponse
    {
        try {
            $distribution = Persona::select('universe_type', DB::raw('count(*) as count'))
                ->groupBy('universe_type')
                ->get();

            $totalPersonas = Persona::count();

            $data = $distribution->map(function($item) use ($totalPersonas) {
                return [
                    'universe' => $item->universe_type,
                    'count' => $item->count,
                    'percentage' => $totalPersonas > 0 ? round(($item->count / $totalPersonas) * 100, 2) : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_personas' => $totalPersonas,
                    'distribution' => $data,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Universe distribution error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distribución de universos.'
            ], 500);
        }
    }

    /**
     * Get QR code analytics for an event
     */
    public function qrAnalytics($eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);

            $qrCodes = QrCode::where('event_id', $eventId)
                ->select('type', 'scan_count', 'is_active')
                ->get();

            $analytics = $qrCodes->groupBy('type')->map(function($group, $type) {
                return [
                    'type' => $type,
                    'total_codes' => $group->count(),
                    'active_codes' => $group->where('is_active', true)->count(),
                    'total_scans' => $group->sum('scan_count'),
                    'average_scans' => round($group->avg('scan_count'), 2),
                ];
            })->values();

            $totalScans = $qrCodes->sum('scan_count');

            return response()->json([
                'success' => true,
                'data' => [
                    'event_id' => $event->id,
                    'event_name' => $event->detail,
                    'total_qr_codes' => $qrCodes->count(),
                    'total_scans' => $totalScans,
                    'by_type' => $analytics,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('QR analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener análisis de códigos QR.'
            ], 500);
        }
    }

    /**
     * Get points distribution report
     */
    public function pointsDistribution(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $query = BonusPointHistory::query();

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $history = $query->get();

            $totalPointsAwarded = $history->where('points_awarded', '>', 0)->sum('points_awarded');
            $totalPointsRedeemed = abs($history->where('points_awarded', '<', 0)->sum('points_awarded'));

            $byType = $history->groupBy('type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_points' => $group->sum('points_awarded'),
                ];
            });

            $topEarners = Persona::select('personas.id', 'personas.nombre', 'personas.apellido', 'personas.loyalty_balance', 'personas.universe_type')
                ->orderBy('personas.loyalty_balance', 'desc')
                ->limit(10)
                ->get()
                ->map(function($persona, $index) {
                    return [
                        'rank' => $index + 1,
                        'name' => $persona->nombre . ' ' . $persona->apellido,
                        'points' => $persona->loyalty_balance,
                        'universe' => $persona->universe_type,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_awarded' => $totalPointsAwarded,
                        'total_redeemed' => $totalPointsRedeemed,
                        'net_points' => $totalPointsAwarded - $totalPointsRedeemed,
                    ],
                    'by_transaction_type' => $byType,
                    'top_earners' => $topEarners,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Points distribution error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener distribución de puntos.'
            ], 500);
        }
    }

    /**
     * Export attendees to CSV
     */
    public function exportAttendees($eventId): JsonResponse
    {
        try {
            $event = Event::findOrFail($eventId);

            $attendees = EventAttendee::where('event_id', $eventId)
                ->with('persona')
                ->get();

            $csvData = [];
            $csvData[] = ['Cédula', 'Nombre', 'Apellido', 'Teléfono', 'Ciudad', 'Universo', 'Estado', 'Hora Entrada', 'Hora Salida', 'Duración (min)'];

            foreach ($attendees as $attendee) {
                $duration = null;
                if ($attendee->entry_timestamp && $attendee->exit_timestamp) {
                    $entry = \Carbon\Carbon::parse($attendee->entry_timestamp);
                    $exit = \Carbon\Carbon::parse($attendee->exit_timestamp);
                    $duration = $exit->diffInMinutes($entry);
                }

                $csvData[] = [
                    $attendee->persona->cedula ?? '',
                    $attendee->persona->nombre ?? '',
                    $attendee->persona->apellido ?? '',
                    $attendee->persona->numero_celular ?? '',
                    $attendee->persona->ciudad ?? '',
                    $attendee->persona->universe_type ?? '',
                    $attendee->attendance_status ?? '',
                    $attendee->entry_timestamp ?? '',
                    $attendee->exit_timestamp ?? '',
                    $duration ?? '',
                ];
            }

            $filename = "attendees_event_{$eventId}_" . now()->format('Y-m-d_His') . ".csv";
            $filepath = storage_path('app/public/exports/' . $filename);

            // Create directory if it doesn't exist
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            $file = fopen($filepath, 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);

            return response()->json([
                'success' => true,
                'message' => 'Exportación exitosa.',
                'data' => [
                    'filename' => $filename,
                    'url' => url('storage/exports/' . $filename),
                    'total_records' => count($csvData) - 1,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Export attendees error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar asistentes.'
            ], 500);
        }
    }
}
