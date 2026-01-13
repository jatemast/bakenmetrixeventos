<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupAttendance;
use App\Models\Persona;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * GroupController
 * 
 * Manages U2 (Groups/Guilds) functionality:
 * - Group CRUD operations
 * - Member management
 * - Group attendance tracking
 * - Group-level reporting
 */
class GroupController extends Controller
{
    /**
     * Get all groups
     */
    public function index(Request $request): JsonResponse
    {
        $query = Group::query();

        // Filters
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        if ($request->has('region')) {
            $query->inRegion($request->region);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Include relationships
        $query->with(['leader', 'subLeader']);

        // Pagination
        $limit = $request->input('limit', 50);
        $groups = $query->orderBy('name')->paginate($limit);

        return response()->json($groups);
    }

    /**
     * Create a new group
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:guild,community,neighborhood,organization,other',
            'description' => 'nullable|string',
            'municipality' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'leader_persona_id' => 'nullable|exists:personas,id',
            'sub_leader_persona_id' => 'nullable|exists:personas,id',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();
        
        // Generate unique code
        if (!isset($data['code'])) {
            $data['code'] = Group::generateCode();
        }

        $group = Group::create($data);

        // If leader specified, update their universe to U2
        if ($group->leader_persona_id) {
            $leader = Persona::find($group->leader_persona_id);
            if ($leader && $leader->universe_type !== 'U2') {
                $leader->update(['universe_type' => 'U2']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Group created successfully',
            'group' => $group->load(['leader', 'subLeader']),
        ], 201);
    }

    /**
     * Get a specific group with details
     */
    public function show(string $id): JsonResponse
    {
        $group = Group::with([
            'leader',
            'subLeader',
            'activeMembers',
            'attendances.event',
        ])->findOrFail($id);

        $stats = $group->getStats();

        return response()->json([
            'group' => $group,
            'stats' => $stats,
        ]);
    }

    /**
     * Update a group
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:guild,community,neighborhood,organization,other',
            'description' => 'nullable|string',
            'municipality' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'leader_persona_id' => 'nullable|exists:personas,id',
            'sub_leader_persona_id' => 'nullable|exists:personas,id',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive,suspended,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $group->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Group updated successfully',
            'group' => $group->load(['leader', 'subLeader']),
        ]);
    }

    /**
     * Delete a group
     */
    public function destroy(string $id): JsonResponse
    {
        $group = Group::findOrFail($id);
        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Group deleted successfully',
        ]);
    }

    /**
     * Add members to a group
     */
    public function addMembers(Request $request, string $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'persona_ids' => 'required|array',
            'persona_ids.*' => 'exists:personas,id',
            'role' => 'nullable|in:member,sub_leader,coordinator,observer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = $request->input('role', 'member');
        $added = 0;
        $existing = 0;

        foreach ($request->persona_ids as $personaId) {
            // Check if already a member
            $exists = GroupMember::where('group_id', $group->id)
                ->where('persona_id', $personaId)
                ->exists();

            if ($exists) {
                $existing++;
                continue;
            }

            // Add member
            GroupMember::create([
                'group_id' => $group->id,
                'persona_id' => $personaId,
                'role' => $role,
                'joined_at' => now(),
                'is_active' => true,
            ]);

            // Update persona universe to U2
            $persona = Persona::find($personaId);
            if ($persona) {
                $persona->update([
                    'universe_type' => 'U2',
                    'group_id' => $group->id,
                ]);
            }

            $added++;
        }

        // Update member counts
        $group->updateMemberCounts();

        return response()->json([
            'success' => true,
            'message' => "Added {$added} members, {$existing} already existed",
            'stats' => [
                'added' => $added,
                'existing' => $existing,
                'total_members' => $group->member_count,
            ],
        ]);
    }

    /**
     * Remove a member from a group
     */
    public function removeMember(string $groupId, string $personaId): JsonResponse
    {
        $group = Group::findOrFail($groupId);
        
        $member = GroupMember::where('group_id', $groupId)
            ->where('persona_id', $personaId)
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found in this group',
            ], 404);
        }

        $member->deactivate();

        // Update persona
        $persona = Persona::find($personaId);
        if ($persona && $persona->group_id == $groupId) {
            $persona->update(['group_id' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Member removed from group',
        ]);
    }

    /**
     * Get group members
     */
    public function members(string $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $members = $group->members()
            ->withPivot([
                'role',
                'joined_at',
                'is_active',
                'events_attended',
                'points_contributed',
            ])
            ->orderBy('group_members.joined_at', 'desc')
            ->get();

        return response()->json([
            'group_id' => $group->id,
            'group_name' => $group->name,
            'total_members' => $members->count(),
            'active_members' => $members->where('pivot.is_active', true)->count(),
            'members' => $members,
        ]);
    }

    /**
     * Track group attendance for an event
     */
    public function trackEventAttendance(Request $request, string $groupId, string $eventId): JsonResponse
    {
        $group = Group::findOrFail($groupId);
        $event = Event::findOrFail($eventId);

        // Get or create group attendance record
        $attendance = GroupAttendance::firstOrCreate(
            [
                'group_id' => $groupId,
                'event_id' => $eventId,
            ],
            [
                'members_invited' => $request->input('members_invited', $group->active_member_count),
                'invited_at' => now(),
                'status' => 'invited',
            ]
        );

        // Calculate current metrics
        $attendance->calculateMetrics();

        return response()->json([
            'success' => true,
            'attendance' => $attendance,
        ]);
    }

    /**
     * Get group attendance history
     */
    public function attendanceHistory(string $id): JsonResponse
    {
        $group = Group::findOrFail($id);

        $attendances = GroupAttendance::where('group_id', $id)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'group_id' => $group->id,
            'group_name' => $group->name,
            'total_events' => $attendances->count(),
            'attendances' => $attendances,
        ]);
    }

    /**
     * Distribute group points for an event
     */
    public function distributeEventPoints(string $groupId, string $eventId): JsonResponse
    {
        $attendance = GroupAttendance::where('group_id', $groupId)
            ->where('event_id', $eventId)
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Group attendance record not found',
            ], 404);
        }

        if ($attendance->points_distributed) {
            return response()->json([
                'success' => false,
                'message' => 'Points already distributed for this group-event',
            ]);
        }

        // Calculate and distribute
        $attendance->distributePoints();

        return response()->json([
            'success' => true,
            'message' => 'Points distributed successfully',
            'points_earned' => $attendance->group_points_earned,
            'attendance_rate' => $attendance->attendance_rate,
        ]);
    }

    /**
     * Get group statistics and leaderboard
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $metric = $request->input('metric', 'loyalty_balance');
        $limit = $request->input('limit', 10);

        $query = Group::where('is_active', true)
            ->where('status', 'active');

        if ($metric === 'loyalty_balance') {
            $query->orderByDesc('loyalty_balance');
        } else if ($metric === 'member_count') {
            $query->orderByDesc('active_member_count');
        }

        $groups = $query->limit($limit)->get();

        $leaderboard = $groups->map(function ($group, $index) {
            $stats = $group->getStats();
            return [
                'rank' => $index + 1,
                'group_id' => $group->id,
                'group_name' => $group->name,
                'group_code' => $group->code,
                'type' => $group->type,
                'region' => $group->region,
                'stats' => $stats,
            ];
        });

        return response()->json([
            'metric' => $metric,
            'leaderboard' => $leaderboard,
        ]);
    }
}
