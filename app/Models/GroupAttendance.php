<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GroupAttendance Model
 * 
 * Tracks group-level attendance for events
 * Provides collective metrics for how groups participate in events
 */
class GroupAttendance extends Model
{
    protected $fillable = [
        'group_id',
        'event_id',
        'members_invited',
        'members_registered',
        'members_attended',
        'attendance_rate',
        'group_points_earned',
        'points_distributed',
        'status',
        'invited_at',
        'first_checkin_at',
        'last_checkout_at',
    ];

    protected $casts = [
        'members_invited' => 'integer',
        'members_registered' => 'integer',
        'members_attended' => 'integer',
        'attendance_rate' => 'decimal:2',
        'group_points_earned' => 'integer',
        'points_distributed' => 'boolean',
        'invited_at' => 'datetime',
        'first_checkin_at' => 'datetime',
        'last_checkout_at' => 'datetime',
    ];

    /**
     * Get the group
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the event
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Calculate and update attendance metrics
     */
    public function calculateMetrics()
    {
        // Get all event attendees from this group
        $attendees = \App\Models\EventAttendee::where('event_id', $this->event_id)
            ->where('group_id', $this->group_id)
            ->get();

        $this->members_registered = $attendees->whereIn('attendance_status', [
            'registered', 'present', 'completed'
        ])->count();

        $this->members_attended = $attendees->where('attendance_status', 'completed')->count();

        // Calculate attendance rate
        if ($this->members_invited > 0) {
            $this->attendance_rate = ($this->members_attended / $this->members_invited) * 100;
        }

        $this->save();
    }

    /**
     * Distribute group points
     */
    public function distributePoints()
    {
        if ($this->points_distributed) {
            return;
        }

        $event = $this->event;
        
        // Base points: attendees * event points
        $basePoints = $this->members_attended * ($event->bonus_points_for_attendee ?? 50);
        
        // Bonus: if attendance rate > 80%, extra 20% bonus
        $bonusMultiplier = 1.0;
        if ($this->attendance_rate >= 80) {
            $bonusMultiplier = 1.2;
        } else if ($this->attendance_rate >= 60) {
            $bonusMultiplier = 1.1;
        }
        
        $this->group_points_earned = (int) ($basePoints * $bonusMultiplier);
        
        // Add points to group
        $this->group->addPoints(
            $this->group_points_earned,
            "Event attendance: {$event->detail}"
        );
        
        $this->points_distributed = true;
        $this->save();
    }

    /**
     * Update status based on attendance progress
     */
    public function updateStatus()
    {
        if ($this->members_attended > 0) {
            $this->status = 'completed';
        } else if ($this->members_registered > 0) {
            $this->status = 'registered';
        } else if ($this->members_invited > 0) {
            $this->status = 'invited';
        }
        
        $this->save();
    }
}
