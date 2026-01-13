<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Group Model (U2 - Groups/Guilds)
 * 
 * Represents organized groups or guilds in the system
 * Groups can:
 * - Have multiple members (personas)
 * - Attend events collectively
 * - Earn group-level loyalty points
 * - Have leaders and sub-leaders
 * - Track attendance metrics
 */
class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'municipality',
        'estado',
        'region',
        'leader_persona_id',
        'sub_leader_persona_id',
        'contact_email',
        'contact_phone',
        'member_count',
        'active_member_count',
        'loyalty_balance',
        'is_active',
        'status',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'member_count' => 'integer',
        'active_member_count' => 'integer',
        'loyalty_balance' => 'integer',
    ];

    /**
     * Get the main leader of the group
     */
    public function leader()
    {
        return $this->belongsTo(Persona::class, 'leader_persona_id');
    }

    /**
     * Get the sub-leader of the group
     */
    public function subLeader()
    {
        return $this->belongsTo(Persona::class, 'sub_leader_persona_id');
    }

    /**
     * Get all members of the group (through pivot table)
     */
    public function members()
    {
        return $this->belongsToMany(Persona::class, 'group_members')
            ->withPivot([
                'role',
                'joined_at',
                'left_at',
                'is_active',
                'events_attended',
                'points_contributed',
            ])
            ->withTimestamps();
    }

    /**
     * Get only active members
     */
    public function activeMembers()
    {
        return $this->members()->wherePivot('is_active', true);
    }

    /**
     * Get group attendances (events this group participated in)
     */
    public function attendances()
    {
        return $this->hasMany(GroupAttendance::class);
    }

    /**
     * Get personas who have this as their default group
     */
    public function defaultGroupPersonas()
    {
        return $this->hasMany(Persona::class, 'group_id');
    }

    /**
     * Scope: Only active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active');
    }

    /**
     * Scope: Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by region
     */
    public function scopeInRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Update member counts
     */
    public function updateMemberCounts()
    {
        $this->member_count = $this->members()->count();
        $this->active_member_count = $this->activeMembers()->count();
        $this->save();
    }

    /**
     * Add points to group balance
     */
    public function addPoints(int $points, string $reason = null)
    {
        $this->increment('loyalty_balance', $points);
        
        // Log in bonus history if reason provided
        if ($reason) {
            \App\Models\BonusPointHistory::create([
                'group_id' => $this->id,
                'points' => $points,
                'reason' => $reason,
                'description' => "Group bonus: {$reason}",
            ]);
        }
    }

    /**
     * Get group statistics
     */
    public function getStats()
    {
        return [
            'total_members' => $this->member_count,
            'active_members' => $this->active_member_count,
            'loyalty_balance' => $this->loyalty_balance,
            'events_attended' => $this->attendances()->where('status', 'completed')->count(),
            'total_attendance' => $this->attendances()->sum('members_attended'),
            'average_attendance_rate' => $this->attendances()->avg('attendance_rate'),
        ];
    }

    /**
     * Generate unique group code
     */
    public static function generateCode(): string
    {
        do {
            $code = 'GRP-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (self::where('code', $code)->exists());
        
        return $code;
    }
}
