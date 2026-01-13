<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\MascotaController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Public Registration Routes
Route::post('/public/check-whatsapp', [PublicRegistrationController::class, 'checkWhatsApp']);
Route::post('/public/register', [PublicRegistrationController::class, 'register']);
Route::post('/public/register-event', [PublicRegistrationController::class, 'registerForEvent']);
Route::post('/public/profile', [PublicRegistrationController::class, 'getPersonaProfile']);

// Ruta pública para ver un evento por su código de check-in
Route::get('/events/public/{checkinCode}', [EventController::class, 'showPublic']);

// Rutas de asistencia (check-in/check-out) - Legacy
Route::post('/events/checkin', [AttendanceController::class, 'checkin']);
Route::post('/events/checkout', [AttendanceController::class, 'checkout']);

// QR Code Scanning Routes (NEW - Universe-aware)
Route::post('/qr/scan', [QrScanController::class, 'scan']); // Universal scan endpoint
Route::post('/qr/scan/manual-checkin', [QrScanController::class, 'manualCheckIn']);
Route::post('/qr/scan/manual-checkout', [QrScanController::class, 'manualCheckOut']);
Route::get('/qr/{code}/details', [QrScanController::class, 'qrDetails']);

// WhatsApp AI Context Resolution Routes (for n8n integration)
Route::post('/whatsapp/resolve-event-context', [WhatsAppController::class, 'resolveEventContext']);
Route::post('/whatsapp/log-conversation', [WhatsAppController::class, 'logAiConversation']);

// Invitation Routes (for n8n integration)
Route::post('/invitations/log', [InvitationController::class, 'log']);
Route::post('/events/{id}/invitations-complete', [InvitationController::class, 'complete']);
Route::post('/events/{id}/documents-ready', [EventController::class, 'documentsReady']);

// Campaign Personas & Segmentation Routes (for n8n integration)
Route::get('/campaigns/{id}/personas', [CampaignController::class, 'personas']);
Route::post('/campaigns/{id}/process-segmentation', [CampaignController::class, 'processSegmentation']);
Route::post('/campaigns/{id}/validate-segmentation', [CampaignController::class, 'validateSegmentation']);
Route::get('/campaigns/{id}/preview-segmentation', [CampaignController::class, 'previewSegmentation']);

Route::get('/campaigns/{campaignId}/events', [EventController::class, 'index']);
Route::get('/campaigns/{id}', [CampaignController::class, 'show']);

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
    Route::get('/events/{id}/qr-codes', [EventController::class, 'getQrCodes']);
    
    // Event Post-Processing (Queue-based)
    Route::post('/events/{id}/end', [EventController::class, 'endEvent']);

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
    Route::get('/reports/campaign/{campaignId}', [ReportController::class, 'campaignStats']);
    Route::get('/reports/event/{eventId}/attendance', [ReportController::class, 'eventAttendance']);
    Route::get('/reports/universe-distribution', [ReportController::class, 'universeDistribution']);
    Route::get('/reports/qr-analytics/{eventId}', [ReportController::class, 'qrAnalytics']);
    Route::get('/reports/points-distribution', [ReportController::class, 'pointsDistribution']);
    Route::get('/reports/export/attendees/{eventId}', [ReportController::class, 'exportAttendees']);

    // Mascotas Routes
    Route::apiResource('personas.mascotas', MascotaController::class);

    // Persona Bonus Points
    Route::get('/personas/{persona}/bonus-points', [PersonaController::class, 'bonusPoints']);
    Route::get('/personas/{persona}/bonus-point-history', [PersonaController::class, 'bonusPoints']);
});

// Personas Routes (public)
Route::apiResource('personas', PersonaController::class);