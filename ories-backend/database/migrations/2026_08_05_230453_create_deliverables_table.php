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
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('office_type', ['research', 'innovation', 'extension']);
            
            // Foreign Keys
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnDelete();
            $table->foreignId('head_id')->constrained('users')->cascadeOnDelete(); // Encoded by
            
            // 5-Stage Pipeline Status
            $table->enum('status', [
                'draft', 
                'submitted', 
                'director_endorsed', 
                'mov_upload', 
                'vp_approved'
            ])->default('draft');
            
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
