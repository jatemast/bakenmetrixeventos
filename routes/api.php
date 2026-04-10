<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\BeneficiarioController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\RedemptionController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\MilitantQrController;
use App\Http\Controllers\QrImageController;
use App\Http\Controllers\UserPortalController;
use App\Http\Controllers\EventSlotController;
use App\Http\Controllers\MetricsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

// API routes for BakenMetrix
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Ruta de Prueba para WhatsApp (Borrar en producción)
Route::get('/test-whatsapp/{appointmentId}', function ($appointmentId) {
    $appointment = \App\Models\Appointment::findOrFail($appointmentId);
    $service = app(\App\Services\WhatsAppNotificationService::class);
    $result = $service->sendAppointmentConfirmation($appointment);
    return response()->json(['success' => $result, 'message' => $result ? 'Mensaje enviado a n8n' : 'Error al enviar']);
});

Route::get('/test-broadcast/{campaignId}', [CampaignController::class, 'broadcastInvitations']);

// Event Slots & Appointments (Goal 2 - Vaccination & Generic)
Route::prefix('appointments')->group(function () {
    Route::get('/qr/{qrCode}', [EventSlotController::class, 'showByQr']);
    Route::post('/slots/{slotId}/book', [EventSlotController::class, 'book']);
    Route::post('/start-process', [EventSlotController::class, 'startProcess']);
    Route::post('/{appointmentId}/complete-process', [EventSlotController::class, 'completeProcess']);
});

Route::get('/events/{eventId}/available-slots', [EventSlotController::class, 'getAvailableSlots']);

// Metrics & Reports Dashboard (Admin)
Route::prefix('metrics')->group(function () {
    Route::get('/summary', [MetricsController::class, 'globalSummary']);
    Route::get('/events/{eventId}', [MetricsController::class, 'eventMetrics']);
    Route::get('/realtime/{eventId}', [MetricsController::class, 'realtimeEvent']);
    Route::get('/impact-kpi', [ReportController::class, 'eventImpactKPI']);
    
    // Monitoring
    Route::get('/monitoring/n8n', [\App\Http\Controllers\MonitoringController::class, 'n8nStatus']);
    Route::get('/nightly-report', [MetricsController::class, 'nightlyReport']);

    // Territories
    Route::get('/territories/hierarchy', [\App\Http\Controllers\TerritoryController::class, 'index']);
});

// Public Registration Routes
Route::post('/public/check-whatsapp', [PublicRegistrationController::class, 'checkWhatsApp']);
Route::post('/public/register', [PublicRegistrationController::class, 'register'])->middleware('throttle:10,1');
Route::post('/public/register-event', [PublicRegistrationController::class, 'registerForEvent']);
Route::post('/public/check-active-campaign', [PublicRegistrationController::class, 'checkActiveCampaign']);
Route::get('/public/events', [EventController::class, 'allEvents']);
Route::post('/public/ai-config', [PublicRegistrationController::class, 'getAIConfig']);

// Conversational Session Routes (High-Velocity Master Agent Support)
Route::post('/public/sessions/check', [App\Http\Controllers\Api\ConversationalSessionController::class, 'checkOrStart']);
Route::post('/public/sessions/update-step', [App\Http\Controllers\Api\ConversationalSessionController::class, 'updateStep']);
Route::post('/public/sessions/complete', [App\Http\Controllers\Api\ConversationalSessionController::class, 'complete']);

Route::post('/public/store-super-persona', [PublicRegistrationController::class, 'storeSuperPersona']);
Route::get('/public/events/{id}', [PublicRegistrationController::class, 'getEventDetails']);
Route::get('/public/events/{id}/registration-form', [PublicRegistrationController::class, 'getRegistrationForm']);
Route::get('/public/postal-code/{cp}', [App\Http\Controllers\PostalCodeController::class, 'lookup']);
Route::get('/public/events/reminders-due', [EventController::class, 'getRemindersDue']);
Route::post('/public/profile', [PublicRegistrationController::class, 'getPersonaProfile']);

// User Portal Routes (Citizen Self-Service)
// OTP Authentication (public - no token required)
Route::post('/portal/request-otp', [UserPortalController::class, 'requestOtp'])->middleware('throttle:5,1');
Route::post('/portal/verify-otp', [UserPortalController::class, 'verifyOtp']);

// Secured Portal Endpoints (require X-Portal-Token header)
Route::post('/portal/logout', [UserPortalController::class, 'logout']);
Route::post('/portal/profile', [UserPortalController::class, 'getProfile']);
Route::post('/portal/events/upcoming', [UserPortalController::class, 'getUpcomingEvents']);
Route::post('/portal/events/history', [UserPortalController::class, 'getEventHistory']);
Route::post('/portal/events/{eventId}', [UserPortalController::class, 'getEventDetails']);
Route::post('/portal/loyalty/history', [UserPortalController::class, 'getLoyaltyHistory']);
Route::post('/portal/redemptions', [UserPortalController::class, 'getRedemptions']);
Route::post('/portal/preferences', [UserPortalController::class, 'updatePreferences']);

// Ruta pública para ver un evento por su código de check-in
Route::get('/events/public/{checkinCode}', [EventController::class, 'showPublic']);

// Rutas de asistencia (check-in/check-out) - Legacy
Route::post('/events/checkin-by-code', [AttendanceController::class, 'checkinByCode']);
Route::post('/events/checkin', [AttendanceController::class, 'checkin'])->middleware('throttle:30,1');
Route::post('/events/checkout', [AttendanceController::class, 'checkout']);

// QR Code Scanning Routes (NEW - Universe-aware)
Route::post('/qr/scan', [QrScanController::class, 'scan']); // Universal scan endpoint
Route::post('/qr/scan/manual-checkin', [QrScanController::class, 'manualCheckIn']);
Route::post('/qr/scan/manual-checkout', [QrScanController::class, 'manualCheckOut']);
Route::get('/qr/{code}/details', [QrScanController::class, 'qrDetails']);

// QR Code Data Endpoint (for n8n - fetches stored QR images)
Route::get('/events/{eventId}/qr-data', [QrImageController::class, 'getEventQrData']);
Route::get('/qr/crm-registration', [QrImageController::class, 'getCrmRegistrationQr']);

// WhatsApp Routes
Route::post('/whatsapp/resolve-event-context', [WhatsAppController::class, 'resolveEventContext']);
Route::post('/whatsapp/log-conversation', [WhatsAppController::class, 'logAiConversation']);
Route::post('/whatsapp/attendance', [WhatsAppController::class, 'handleAttendance']);
Route::get('/whatsapp/active-events', [WhatsAppController::class, 'getActiveEvents']);
Route::post('/whatsapp/send-confirmation', [WhatsAppController::class, 'sendConfirmation']);
Route::post('/whatsapp/set-state', [WhatsAppController::class, 'setState']);
Route::get('/whatsapp/get-state', [WhatsAppController::class, 'getState']);

// Invitation Routes
Route::post('/invitations/log', [InvitationController::class, 'log']);
Route::post('/events/{id}/invitations-complete', [InvitationController::class, 'complete']);
Route::post('/events/{id}/documents-ready', [EventController::class, 'documentsReady']);
Route::get('/events/{id}/audience', [EventController::class, 'getTargetedAudience']);
Route::post('/events/{id}/invite', [EventController::class, 'sendEventInvitations']);

// Campaign Personas & Segmentation Routes
Route::get('/campaigns/{id}/personas', [CampaignController::class, 'personas']);
Route::post('/campaigns/{id}/process-segmentation', [CampaignController::class, 'processSegmentation']);
Route::post('/campaigns/{id}/validate-segmentation', [CampaignController::class, 'validateSegmentation']);
Route::get('/campaigns/{id}/preview-segmentation', [CampaignController::class, 'previewSegmentation']);
Route::post('/campaigns/{id}/broadcast', [CampaignController::class, 'broadcastInvitations']);
Route::get('/campaigns/{id}/militant-qrs-distribution', [CampaignController::class, 'getMilitantQrsForDistribution']);



Route::get('/events/{id}', [EventController::class, 'show']);

// Bonus Points Routes (U3 - Leaders)
Route::get('/leaders/{personaId}/stats', [BonusController::class, 'leaderStats']);
Route::get('/leaders/{personaId}/events/{eventId}/bonus-preview', [BonusController::class, 'leaderBonusPreview']);
Route::get('/leaders/leaderboard', [BonusController::class, 'leaderLeaderboard']);

// Groups Routes (U2 - Groups/Guilds) - Public read access
Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/{id}', [GroupController::class, 'show']);
Route::get('/groups/{id}/members', [GroupController::class, 'members']);
Route::get('/groups/{id}/attendance-history', [GroupController::class, 'attendanceHistory']);
Route::get('/groups/leaderboard', [GroupController::class, 'leaderboard']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Campaign Routes
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{id}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

    // Event Routes
    Route::get('/events', [EventController::class, 'allEvents']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/campaigns/{campaignId}/events', [EventController::class, 'index']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);
    Route::get('/events/{id}/qr-codes', [EventController::class, 'getQrCodes']);

    // Event Post-Processing (Queue-based)
    Route::post('/events/{id}/end', [EventController::class, 'endEvent']);

    // Event Slots Generation
    Route::post('/events/{eventId}/generate-slots', [EventSlotController::class, 'generate']);

    // Bonus Points Management (Admin)
    Route::post('/events/{eventId}/distribute-bonuses', [BonusController::class, 'distributeEventBonuses']);
    Route::post('/events/{eventId}/recalculate-bonuses', [BonusController::class, 'recalculateEventBonuses']);

    // Groups Management (Admin)
    Route::post('/groups', [GroupController::class, 'store']);
    Route::put('/groups/{id}', [GroupController::class, 'update']);
    Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
    Route::post('/groups/{id}/members', [GroupController::class, 'addMembers']);
    Route::delete('/groups/{groupId}/members/{personaId}', [GroupController::class, 'removeMember']);
    Route::post('/groups/{groupId}/events/{eventId}/track-attendance', [GroupController::class, 'trackEventAttendance']);
    Route::post('/groups/{groupId}/events/{eventId}/distribute-points', [GroupController::class, 'distributeEventPoints']);

    // Loyalty Routes
    Route::get('/loyalty/balance/{personaId}', [LoyaltyController::class, 'getBalance']);
    Route::get('/loyalty/history/{personaId}', [LoyaltyController::class, 'getHistory']);
    Route::post('/loyalty/points/add', [LoyaltyController::class, 'addPoints']);
    Route::post('/loyalty/points/redeem', [LoyaltyController::class, 'redeemPoints']);
    Route::get('/loyalty/leaderboard', [LoyaltyController::class, 'getLeaderboard']);
    Route::get('/personas/loyalty-eligible', [LoyaltyController::class, 'getEligiblePersonas']);
    Route::post('/events/{eventId}/distribute-points', [LoyaltyController::class, 'distributeEventPoints']);

    // Redemption Routes (Voucher System)
    Route::post('/redemptions/validate', [RedemptionController::class, 'validateVoucher']);
    Route::get('/redemptions/persona/{personaId}', [RedemptionController::class, 'getPersonaRedemptions']);
    Route::get('/redemptions/voucher/{voucherCode}', [RedemptionController::class, 'getVoucherDetails']);
    Route::post('/redemptions/{id}/cancel', [RedemptionController::class, 'cancelRedemption']);
    Route::get('/redemptions/pending', [RedemptionController::class, 'getPendingRedemptions']);
    Route::get('/redemptions/stats', [RedemptionController::class, 'getStats']);

    // Report Routes
    Route::get('/reports/dashboard', [ReportController::class, 'dashboardOverview']);
    Route::get('/reports/overview', [ReportController::class, 'reportsOverview']);
    Route::get('/reports/export', [ReportController::class, 'exportReports']);
    Route::get('/reports/campaign/{campaignId}', [ReportController::class, 'campaignStats']);
    Route::get('/reports/event/{eventId}/attendance', [ReportController::class, 'eventAttendance']);
    Route::get('/reports/universe-distribution', [ReportController::class, 'universeDistribution']);
    Route::get('/reports/qr-analytics/{eventId}', [ReportController::class, 'qrAnalytics']);
    Route::get('/reports/points-distribution', [ReportController::class, 'pointsDistribution']);
    Route::get('/reports/export/attendees/{eventId}', [ReportController::class, 'exportAttendees']);

    // Beneficiarios (Generic)
    Route::apiResource('personas.beneficiarios', BeneficiarioController::class);
    
    // Legacy support for Mascotas
    Route::apiResource('personas.mascotas', BeneficiarioController::class);

    // Persona Bonus Points
    Route::get('/personas/{persona}/bonus-points', [PersonaController::class, 'bonusPoints']);
    Route::get('/personas/{persona}/bonus-point-history', [PersonaController::class, 'bonusPoints']);
    
    // Militant QR Management (Admin only)
    Route::get('/campaigns/{campaignId}/militant-qrs', [MilitantQrController::class, 'getCampaignMilitantQrs']);
    Route::post('/campaigns/{campaignId}/militant-qrs/generate', [MilitantQrController::class, 'generateCampaignMilitantQrs']);
    Route::post('/campaigns/{campaignId}/militant-qrs/persona/{personaId}/regenerate', [MilitantQrController::class, 'regenerateMilitantQr']);
    Route::get('/campaigns/{campaignId}/militant-qrs/stats', [MilitantQrController::class, 'getMilitantQrStats']);
});

// Personas Routes (public)
Route::apiResource('personas', PersonaController::class);