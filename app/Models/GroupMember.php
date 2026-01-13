<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GroupMember Model
 * 
 * Pivot model for group membership
 * Tracks persona membership in groups with additional metadata
 */
class GroupMember extends Model
{
    protected $fillable = [
        'group_id',
        'persona_id',
        'role',
        'joined_at',
        'left_at',
        'is_active',
        'events_attended',
        'points_contributed',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
        'is_active' => 'boolean',
        'events_attended' => 'integer',
        'points_contributed' => 'integer',
    ];

    /**
     * Get the group
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the persona
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Scope: Only active memberships
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNull('left_at');
    }

    /**
     * Deactivate membership
     */
    public function deactivate()
    {
        $this->update([
            'is_active' => false,
            'left_at' => now(),
        ]);
        
        // Update group member counts
        $this->group->updateMemberCounts();
    }

    /**
     * Reactivate membership
     */
    public function reactivate()
    {
        $this->update([
            'is_active' => true,
            'left_at' => null,
        ]);
        
        // Update group member counts
        $this->group->updateMemberCounts();
    }
}
