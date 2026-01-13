<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'campaign_id',
        'detail',
        'date',
        'time',
        'duration_hours',
        'max_capacity',
        'target_universes',
        'responsible',
        'email',
        'whatsapp',
        'dynamic',
        'pdf_path',
        'street',
        'number',
        'neighborhood',
        'postal_code',
        'municipality',
        'state',
        'country',
        'checkin_code',
        'checkout_code',
        'bonus_points_for_attendee',
        'bonus_points_for_leader',
        'status',
        'registered_count',
        'checked_in_count',
        'attended_count',
        'ai_knowledge_ready',
        'invitations_sent',
        'points_distributed',
        'grace_period_hours',
        'ended_at',
        'auto_close_scheduled',
        'points_distribution_scheduled',
    ];

    protected $casts = [
        'target_universes' => 'array',
        'ai_knowledge_ready' => 'boolean',
        'invitations_sent' => 'boolean',
        'points_distributed' => 'boolean',
        'auto_close_scheduled' => 'boolean',
        'points_distribution_scheduled' => 'boolean',
        'ended_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function attendees()
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function bonusPointsHistory()
    {
        return $this->hasMany(BonusPointHistory::class);
    }

    public function qrCodes()
    {
        return $this->hasMany(QrCode::class);
    }

    public function repository()
    {
        return $this->hasOne(EventRepository::class);
    }

    /**
     * Schedule automated post-event processing
     * 
     * Call this when event ends (via API or manual end) to schedule:
     * 1. Auto-checkout attendees after grace period
     * 2. Points distribution after auto-checkout
     * 
     * Jobs are delayed based on event end time + grace_period_hours
     * 
     * @param \DateTime|null $endTime Optional custom end time (defaults to now)
     * @return void
     */
    public function schedulePostEventProcessing($endTime = null): void
    {
        $endTime = $endTime ?? now();
        
        // Set ended_at if not already set
        if (!$this->ended_at) {
            $this->update(['ended_at' => $endTime]);
        }

        $gracePeriodHours = $this->grace_period_hours ?? 1;
        $delayInMinutes = $gracePeriodHours * 60;

        // Schedule auto-checkout (runs at grace period end)
        if (!$this->auto_close_scheduled) {
            \App\Jobs\AutoCheckoutEventAttendees::dispatch($this)
                ->delay(now()->addMinutes($delayInMinutes));

            $this->update(['auto_close_scheduled' => true]);

            \Log::info("Scheduled auto-checkout for event {$this->id} at " . 
                      now()->addMinutes($delayInMinutes)->toDateTimeString());
        }

        // Note: Points distribution is automatically triggered by AutoCheckoutEventAttendees job
        // This ensures checkout happens before points calculation
    }

    /**
     * Check if event should be ended based on date + time + duration
     * 
     * @return bool
     */
    public function shouldBeEnded(): bool
    {
        if ($this->ended_at) {
            return false; // Already ended
        }

        if (!$this->date || !$this->time || !$this->duration_hours) {
            return false; // Missing required fields
        }

        $scheduledEndTime = \Carbon\Carbon::parse($this->date . ' ' . $this->time)
            ->addHours($this->duration_hours);

        return now()->gte($scheduledEndTime);
    }

    /**
     * Get the calculated end time for this event
     * 
     * @return \Carbon\Carbon|null
     */
    public function getCalculatedEndTime(): ?\Carbon\Carbon
    {
        if (!$this->date || !$this->time || !$this->duration_hours) {
            return null;
        }

        return \Carbon\Carbon::parse($this->date . ' ' . $this->time)
            ->addHours($this->duration_hours);
    }
}

