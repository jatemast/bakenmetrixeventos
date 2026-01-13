<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration creates tables for U2 (Groups/Guilds) universe functionality
     * 
     * Groups are organized collections of personas that:
     * - Attend events collectively
     * - Have group-level metrics and reporting
     * - Can have group leaders and sub-leaders
     * - Earn collective bonus points
     */
    public function up(): void
    {
        // Main groups table
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name')->comment('Group or guild name');
            $table->string('code')->unique()->comment('Unique group code (e.g., GRP-001)');
            $table->text('description')->nullable();
            
            // Classification
            $table->enum('type', ['guild', 'community', 'neighborhood', 'organization', 'other'])
                ->default('community')
                ->comment('Type of group');
            
            // Location
            $table->string('municipality')->nullable();
            $table->string('estado')->nullable();
            $table->string('region')->nullable();
            
            // Leadership
            $table->foreignId('leader_persona_id')
                ->nullable()
                ->constrained('personas')
                ->onDelete('set null')
                ->comment('Main group leader');
            
            $table->foreignId('sub_leader_persona_id')
                ->nullable()
                ->constrained('personas')
                ->onDelete('set null')
                ->comment('Secondary group leader');
            
            // Contact
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            
            // Metrics
            $table->integer('member_count')->default(0)->comment('Total members in group');
            $table->integer('active_member_count')->default(0)->comment('Active members');
            $table->integer('loyalty_balance')->default(0)->comment('Collective group points');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['active', 'inactive', 'suspended', 'archived'])
                ->default('active');
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Additional group data');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code');
            $table->index('type');
            $table->index('is_active');
            $table->index('region');
            $table->index(['municipality', 'estado']);
        });

        // Group members pivot table
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');
            
            $table->foreignId('persona_id')
                ->constrained('personas')
                ->onDelete('cascade');
            
            // Membership details
            $table->enum('role', ['member', 'sub_leader', 'coordinator', 'observer'])
                ->default('member')
                ->comment('Role within the group');
            
            $table->date('joined_at')->default(now());
            $table->date('left_at')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            // Contribution tracking
            $table->integer('events_attended')->default(0);
            $table->integer('points_contributed')->default(0)
                ->comment('Points earned by this member for the group');
            
            $table->timestamps();
            
            // Indexes and constraints
            $table->unique(['group_id', 'persona_id'], 'unique_group_member');
            $table->index('persona_id');
            $table->index('is_active');
        });

        // Group event attendance (collective attendance tracking)
        Schema::create('group_attendances', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('group_id')
                ->constrained('groups')
                ->onDelete('cascade');
            
            $table->foreignId('event_id')
                ->constrained('events')
                ->onDelete('cascade');
            
            // Attendance metrics
            $table->integer('members_invited')->default(0);
            $table->integer('members_registered')->default(0);
            $table->integer('members_attended')->default(0);
            $table->decimal('attendance_rate', 5, 2)->default(0)
                ->comment('Percentage of members who attended');
            
            // Points
            $table->integer('group_points_earned')->default(0)
                ->comment('Collective points for group');
            $table->boolean('points_distributed')->default(false);
            
            // Status
            $table->enum('status', ['invited', 'registered', 'attending', 'completed'])
                ->default('invited');
            
            // Timestamps
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('first_checkin_at')->nullable();
            $table->timestamp('last_checkout_at')->nullable();
            
            $table->timestamps();
            
            // Indexes and constraints
            $table->unique(['group_id', 'event_id'], 'unique_group_event');
            $table->index('status');
        });

        // Add group_id to personas table (optional: persona can belong to default group)
        Schema::table('personas', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('universe_type')
                ->constrained('groups')
                ->onDelete('set null')
                ->comment('Default group membership for U2 personas');
        });

        // Add group_id to event_attendees (track which group brought this attendee)
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('leader_id')
                ->constrained('groups')
                ->onDelete('set null')
                ->comment('Group that brought this attendee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });

        // Drop tables in reverse order
        Schema::dropIfExists('group_attendances');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
