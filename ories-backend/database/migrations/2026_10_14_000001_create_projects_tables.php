<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A research, innovation, or extension undertaking tracked from proposal onward
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('office', 20); // research, innovation, extension
            $table->text('abstract')->nullable();
            $table->string('keywords')->nullable();
            $table->foreignId('lead_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            // Only verified values are stored here; pending changes live in project_updates
            $table->string('stage', 20)->default('proposal'); // proposal, ongoing, completed, terminated
            $table->string('verification_status', 20)->default('pending'); // pending, verified, returned

            $table->string('funding_source')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('proposed_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['office', 'stage']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name'); // kept for collaborators without accounts
            $table->string('role', 30)->default('member'); // lead, member
            $table->timestamps();
        });

        // Contributor tagging: credited units, campuses, and external partners
        Schema::create('project_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('type', 20); // unit, campus, partner
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->string('label');
            $table->timestamps();
        });

        // Verified results of a project: publications, commercialization, policy, technology transfer
        Schema::create('project_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('type', 30); // publication, presentation, commercialization, policy, technology_transfer
            $table->string('title');
            $table->string('venue')->nullable();        // journal, conference, adopting agency, or partner
            $table->string('indexing', 20)->nullable(); // scopus, wos, aci, other, none
            $table->date('date')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('citations')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Every change a faculty member makes is submitted here and only applied after verification
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // proposal, stage, outcome
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20)->nullable();
            $table->json('data')->nullable();
            $table->text('remarks')->nullable();
            $table->json('duplicate_matches')->nullable();
            $table->string('status', 20)->default('pending'); // pending, verified, returned
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Means of Verification files: stored locally first, then backed up to Google Drive
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_update_id')->nullable()->constrained('project_updates')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('source', 20)->default('upload'); // upload, drive_import
            $table->string('drive_file_id')->nullable();
            $table->string('drive_status', 20)->default('pending'); // pending, synced, failed, disabled
            $table->timestamp('drive_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // recipient
            $table->string('type', 30);                 // participation, completion, publication, utilization
            $table->string('purpose', 40)->nullable();  // promotion, reclassification, ipcr, other
            $table->text('request_remarks')->nullable();
            $table->string('status', 20)->default('requested'); // requested, issued, declined
            $table->string('serial_no')->nullable()->unique();
            $table->string('verification_code', 40)->nullable()->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->timestamps();
        });

        // Office targets compared against accomplishments in OPCR-style reports
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('office', 20);
            $table->string('metric', 40);
            $table->unsignedInteger('value');
            $table->timestamps();

            $table->unique(['year', 'office', 'metric']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('targets');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('project_outcomes');
        Schema::dropIfExists('project_tags');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
