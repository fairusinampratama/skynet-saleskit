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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('task_type');
            $table->string('status')->default('waiting');
            $table->text('technician_notes')->nullable();
            $table->string('photo_evidence')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
