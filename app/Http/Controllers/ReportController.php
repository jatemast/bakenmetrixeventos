<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\Persona;
use App\Models\QrCode;
use App\Models\BonusPointHistory;
use App\Models\Group;
use App\Models\Redemption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get dashboard overview statistics
     */
    public function dashboardOverview(): JsonResponse
    {
        try {
            // Total personas
            $totalPersonas = Persona::count();
            
            // Total campaigns
            $totalCampaigns = Campaign::count();
            $activeCampaigns = Campaign::where('status', 'active')->count();
            
            // Total events
            $totalEvents = Event::count();
            $activeEvents = Event::where('status', 'active')->count();
            $scheduledEvents = Event::where('status', 'scheduled')
                ->where('date', '>=', now()->toDateString())
                ->count();
            $completedEvents = Event::where('status', 'completed')->count();
            
            // Today's stats
            $today = Carbon::today();
            $qrScansToday = QrCode::whereDate('updated_at', $today)
                ->where('scan_count', '>', 0)
                ->sum(DB::raw('CASE WHEN scan_count > 0 THEN 1 ELSE 0 END'));
            
            // Actually count scans from event_attendees for today
            $scansToday = EventAttendee::whereDate('entry_timestamp', $today)->count() +
                          EventAttendee::whereDate('exit_timestamp', $today)->count();
            
            // Points stats
            $totalPointsAwarded = BonusPointHistory::where('points_awarded', '>', 0)->sum('points_awarded');
            $totalPointsRedeemed = abs(BonusPointHistory::where('points_awarded', '<', 0)->sum('points_awarded'));
            $netPoints = $totalPointsAwarded - $totalPointsRedeemed;
            
            // This month stats
            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();
            
            $eventsThisMonth = Event::whereBetween('date', [$monthStart, $monthEnd])->count();
            $attendeesThisMonth = EventAttendee::whereNotNull('entry_timestamp')
                ->whereBetween('entry_timestamp', [$monthStart, $monthEnd])
                ->count();
            $pointsThisMonth = BonusPointHistory::where('points_awarded', '>', 0)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('points_awarded');
            
            // Universe distribution
            $universeDistribution = Persona::select('universe_type', DB::raw('count(*) as count'))
                ->groupBy('universe_type')
                ->get()
                ->mapWithKeys(function ($item) use ($totalPersonas) {
                    return [
                        $item->universe_type => [
                            'count' => $item->count,
                            'percentage' => $totalPersonas > 0 ? round(($item->count / $totalPersonas) * 100, 1) : 0
                        ]
                    ];
                });
            
            // Gender distribution
            $genderDistribution = Persona::select('sexo', DB::raw('count(*) as count'))
                ->groupBy('sexo')
                ->get()
                ->mapWithKeys(function ($item) use ($totalPersonas) {
                    $label = $item->sexo === 'H' ? 'Hombres' : ($item->sexo === 'M' ? 'Mujeres' : 'Otros');
                    return [
                        $label => [
                            'count' => $item->count,
                            'percentage' => $totalPersonas > 0 ? round(($item->count / $totalPersonas) * 100, 1) : 0
                        ]
                    ];
                });
            
            // Recent events
            $recentEvents = Event::with('campaign:id,name')
                ->orderBy('date', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($event) {
                    $registered = EventAttendee::where('event_id', $event->id)->count();
                    $attended = EventAttendee::where('event_id', $event->id)->whereNotNull('entry_timestamp')->count();
                    return [
                        'id' => $event->id,
                        'name' => $event->detail,
                        'date' => $event->date,
                        'campaign' => $event->campaign->name ?? 'N/A',
                        'status' => $event->status,
                        'registered' => $registered,
                        'attended' => $attended,
                        'attendance_rate' => $registered > 0 ? round(($attended / $registered) * 100, 1) : 0
                    ];
                });
            
            // Top leaders by referrals
            $topLeaders = Persona::where('is_leader', true)
                ->orderBy('loyalty_balance', 'desc')
                ->limit(5)
                ->get(['id', 'nombre', 'apellido_paterno', 'loyalty_balance', 'referral_code']);
            
            // Overall attendance rate
            $totalRegistrations = EventAttendee::count();
            $totalAttendance = EventAttendee::whereNotNull('entry_timestamp')->count();
            $overallAttendanceRate = $totalRegistrations > 0 
                ? round(($totalAttendance / $totalRegistrations) * 100, 1) 
                : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_personas' => $totalPersonas,
                        'total_campaigns' => $totalCampaigns,
                        'active_campaigns' => $activeCampaigns,
                        'total_events' => $totalEvents,
                        'active_events' => $activeEvents,
                        'scheduled_events' => $scheduledEvents,
                        'completed_events' => $completedEvents,
                        'scans_today' => $scansToday,
                        'total_points_awarded' => $totalPointsAwarded,
                        'total_points_redeemed' => $totalPointsRedeemed,
                        'net_points' => $netPoints,
                        'overall_attendance_rate' => $overallAttendanceRate
                    ],
                    'this_month' => [
                        'events' => $eventsThisMonth,
                        'attendees' => $attendeesThisMonth,
                        'points_awarded' => $pointsThisMonth
                    ],
                    'universe_distribution' => $universeDistribution,
                    'gender_distribution' => $genderDistribution,
                    'recent_events' => $recentEvents,
                    'top_leaders' => $topLeaders
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Dashboard overview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive reports overview with all data for reports page
     */
    public function reportsOverview(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $campaignId = $request->query('campaign_id');
            $eventId = $request->query('event_id');
            
            // Build base queries with filters
            $eventQuery = Event::query();
            $attendeeQuery = EventAttendee::query();
            $pointsQuery = BonusPointHistory::query();
            
            if ($campaignId) {
                $eventQuery->where('campaign_id', $campaignId);
                $eventIds = Event::where('campaign_id', $campaignId)->pluck('id');
                $attendeeQuery->whereIn('event_id', $eventIds);
                $pointsQuery->whereIn('event_id', $eventIds);
            }
            
            if ($eventId) {
                $attendeeQuery->where('event_id', $eventId);
                $pointsQuery->where('event_id', $eventId);
            }
            
            if ($startDate) {
                $eventQuery->whereDate('date', '>=', $startDate);
                $attendeeQuery->whereDate('created_at', '>=', $startDate);
                $pointsQuery->whereDate('created_at', '>=', $startDate);
            }
            
            if ($endDate) {
                $eventQuery->whereDate('date', '<=', $endDate);
                $attendeeQuery->whereDate('created_at', '<=', $endDate);
                $pointsQuery->whereDate('created_at', '<=', $endDate);
            }
            
            // Summary stats
            $totalEvents = (clone $eventQuery)->count();
            $completedEvents = (clone $eventQuery)->where('status', 'completed')->count();
            $totalRegistrations = (clone $attendeeQuery)->count();
            $totalAttendees = (clone $attendeeQuery)->whereNotNull('entry_timestamp')->count();
            $totalCompleted = (clone $attendeeQuery)->where('attendance_status', 'completed')->count();
            $totalPoints = (clone $pointsQuery)->where('points_awarded', '>', 0)->sum('points_awarded');
            
            // Calculate average attendance rate
            $avgAttendanceRate = $totalRegistrations > 0 
                ? round(($totalAttendees / $totalRegistrations) * 100, 1) 
                : 0;
            
            // Events performance
            $events = Event::with('campaign:id,name')
                ->when($campaignId, fn($q) => $q->where('campaign_id', $campaignId))
                ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                ->orderBy('date', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($event) {
                    $registered = EventAttendee::where('event_id', $event->id)->count();
                    $attended = EventAttendee::where('event_id', $event->id)->whereNotNull('entry_timestamp')->count();
                    $completed = EventAttendee::where('event_id', $event->id)->where('attendance_status', 'completed')->count();
                    
                    return [
                        'id' => $event->id,
                        'name' => $event->detail,
                        'date' => $event->date,
                        'campaign' => $event->campaign->name ?? 'N/A',
                        'campaign_id' => $event->campaign_id,
                        'status' => $event->status,
                        'max_capacity' => $event->max_capacity,
                        'registered' => $registered,
                        'attended' => $attended,
                        'completed' => $completed,
                        'attendance_rate' => $registered > 0 ? round(($attended / $registered) * 100, 1) : 0,
                        'completion_rate' => $attended > 0 ? round(($completed / $attended) * 100, 1) : 0
                    ];
                });
            
            // Universe distribution in attendees
            $universeStats = DB::table('event_attendees')
                ->join('personas', 'event_attendees.persona_id', '=', 'personas.id')
                ->when($eventId, fn($q) => $q->where('event_attendees.event_id', $eventId))
                ->when($startDate, fn($q) => $q->whereDate('event_attendees.created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('event_attendees.created_at', '<=', $endDate))
                ->select('personas.universe_type', DB::raw('count(*) as count'))
                ->groupBy('personas.universe_type')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->universe_type => $item->count];
                });
            
            // Points breakdown
            $pointsSummary = [
                'total_awarded' => (clone $pointsQuery)->where('points_awarded', '>', 0)->sum('points_awarded'),
                'total_redeemed' => abs((clone $pointsQuery)->where('points_awarded', '<', 0)->sum('points_awarded')),
                'by_type' => (clone $pointsQuery)
                    ->select('type', DB::raw('SUM(points_awarded) as total'), DB::raw('COUNT(*) as count'))
                    ->groupBy('type')
                    ->get()
            ];
            
            // Top performers (personas with most attendance)
            $topAttendees = DB::table('event_attendees')
                ->join('personas', 'event_attendees.persona_id', '=', 'personas.id')
                ->whereNotNull('event_attendees.entry_timestamp')
                ->when($startDate, fn($q) => $q->whereDate('event_attendees.entry_timestamp', '>=', $startDate))
                ->when($endDate, fn($q) => $q->whereDate('event_attendees.entry_timestamp', '<=', $endDate))
                ->select(
                    'personas.id',
                    'personas.nombre',
                    'personas.apellido_paterno',
                    'personas.universe_type',
                    'personas.loyalty_balance',
                    DB::raw('COUNT(*) as events_attended')
                )
                ->groupBy('personas.id', 'personas.nombre', 'personas.apellido_paterno', 'personas.universe_type', 'personas.loyalty_balance')
                ->orderByDesc('events_attended')
                ->limit(10)
                ->get();
            
            // Campaigns list for filter
            $campaigns = Campaign::select('id', 'name', 'status', 'start_date', 'end_date')
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Events list for filter
            $eventsList = Event::select('id', 'detail', 'date', 'campaign_id', 'status')
                ->when($campaignId, fn($q) => $q->where('campaign_id', $campaignId))
                ->orderBy('date', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_events' => $totalEvents,
                        'completed_events' => $completedEvents,
                        'total_registrations' => $totalRegistrations,
                        'total_attendees' => $totalAttendees,
                        'total_completed' => $totalCompleted,
                        'total_points' => $totalPoints,
                        'average_attendance_rate' => $avgAttendanceRate
                    ],
                    'events' => $events,
                    'universe_distribution' => $universeStats,
                    'points_summary' => $pointsSummary,
                    'top_attendees' => $topAttendees,
                    'filters' => [
                        'campaigns' => $campaigns,
                        'events' => $eventsList,
                        'applied' => [
                            'campaign_id' => $campaignId,
                            'event_id' => $eventId,
                            'start_date' => $startDate,
                            'end_date' => $endDate
                        ]
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Reports overview error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos de reportes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export reports data as CSV
     */
    public function exportReports(Request $request): JsonResponse
    {
        try {
            $reportType = $request->query('type', 'events');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');
            $campaignId = $request->query('campaign_id');
            $eventId = $request->query('event_id');
            
            $csvData = [];
            $filename = '';
            
            switch ($reportType) {
                case 'events':
                    $csvData[] = ['ID', 'Evento', 'Campaña', 'Fecha', 'Estado', 'Capacidad', 'Registrados', 'Asistentes', 'Completados', 'Tasa Asistencia'];
                    
                    $events = Event::with('campaign:id,name')
                        ->when($campaignId, fn($q) => $q->where('campaign_id', $campaignId))
                        ->when($startDate, fn($q) => $q->whereDate('date', '>=', $startDate))
                        ->when($endDate, fn($q) => $q->whereDate('date', '<=', $endDate))
                        ->orderBy('date', 'desc')
                        ->get();
                    
                    foreach ($events as $event) {
                        $registered = EventAttendee::where('event_id', $event->id)->count();
                        $attended = EventAttendee::where('event_id', $event->id)->whereNotNull('entry_timestamp')->count();
                        $completed = EventAttendee::where('event_id', $event->id)->where('attendance_status', 'completed')->count();
                        
                        $csvData[] = [
                            $event->id,
                            $event->detail,
                            $event->campaign->name ?? 'N/A',
                            $event->date,
                            $event->status,
                            $event->max_capacity,
                            $registered,
                            $attended,
                            $completed,
                            $registered > 0 ? round(($attended / $registered) * 100, 1) . '%' : '0%'
                        ];
                    }
                    $filename = "events_report_" . now()->format('Y-m-d_His') . ".csv";
                    break;
                    
                case 'attendees':
                    $csvData[] = ['Cédula', 'Nombre', 'Apellido', 'Teléfono', 'Universo', 'Evento', 'Fecha', 'Entrada', 'Salida', 'Estado', 'Duración (min)'];
                    
                    $attendees = EventAttendee::with(['persona', 'event'])
                        ->when($eventId, fn($q) => $q->where('event_id', $eventId))
                        ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                        ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                        ->get();
                    
                    foreach ($attendees as $attendee) {
                        $duration = null;
                        if ($attendee->entry_timestamp && $attendee->exit_timestamp) {
                            $entry = Carbon::parse($attendee->entry_timestamp);
                            $exit = Carbon::parse($attendee->exit_timestamp);
                            $duration = $exit->diffInMinutes($entry);
                        }
                        
                        $csvData[] = [
                            $attendee->persona->cedula ?? '',
                            $attendee->persona->nombre ?? '',
                            $attendee->persona->apellido_paterno ?? '',
                            $attendee->persona->numero_celular ?? '',
                            $attendee->persona->universe_type ?? '',
                            $attendee->event->detail ?? '',
                            $attendee->event->date ?? '',
                            $attendee->entry_timestamp ?? '',
                            $attendee->exit_timestamp ?? '',
                            $attendee->attendance_status ?? '',
                            $duration ?? ''
                        ];
                    }
                    $filename = "attendees_report_" . now()->format('Y-m-d_His') . ".csv";
                    break;
                    
                case 'points':
                    $csvData[] = ['ID', 'Persona', 'Tipo', 'Puntos', 'Descripción', 'Evento', 'Fecha'];
                    
                    $points = BonusPointHistory::with(['persona', 'event'])
                        ->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
                        ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate))
                        ->orderBy('created_at', 'desc')
                        ->get();
                    
                    foreach ($points as $point) {
                        $csvData[] = [
                            $point->id,
                            ($point->persona->nombre ?? '') . ' ' . ($point->persona->apellido_paterno ?? ''),
                            $point->type,
                            $point->points_awarded,
                            $point->description ?? '',
                            $point->event->detail ?? 'N/A',
                            $point->created_at->format('Y-m-d H:i:s')
                        ];
                    }
                    $filename = "points_report_" . now()->format('Y-m-d_His') . ".csv";
                    break;
                    
                case 'universe':
                    $csvData[] = ['Universo', 'Total Personas', 'Porcentaje'];
                    
                    $totalPersonas = Persona::count();
                    $distribution = Persona::select('universe_type', DB::raw('count(*) as count'))
                        ->groupBy('universe_type')
                        ->get();
                    
                    foreach ($distribution as $item) {
                        $csvData[] = [
                            $item->universe_type,
                            $item->count,
                            $totalPersonas > 0 ? round(($item->count / $totalPersonas) * 100, 1) . '%' : '0%'
                        ];
                    }
                    $filename = "universe_report_" . now()->format('Y-m-d_His') . ".csv";
                    break;
                    
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de reporte no válido'
                    ], 400);
            }
            
            // Create exports directory if needed
            $exportDir = storage_path('app/public/exports');
            if (!file_exists($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            $filepath = $exportDir . '/' . $filename;
            
            $file = fopen($filepath, 'w');
            // Add BOM for UTF-8 Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
            
            return response()->json([
                'success' => true,
                'message' => 'Exportación exitosa',
                'data' => [
                    'filename' => $filename,
                    'url' => url('storage/exports/' . $filename),
                    'total_records' => count($csvData) - 1
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Export reports error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

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
