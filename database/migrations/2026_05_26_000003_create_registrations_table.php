<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->default('Draft customer');
            $table->string('nik', 32)->nullable()->index();
            $table->string('phone')->default('-');
            $table->text('ktp_full_address')->nullable();
            $table->text('installation_full_address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('ktp_original_file_path')->nullable();
            $table->string('ktp_processed_file_path')->nullable();
            $table->longText('ktp_ocr_raw_text')->nullable();
            $table->json('ktp_ocr_parsed_data')->nullable();
            $table->timestamp('ktp_verified_at')->nullable();
            $table->string('package')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('technician_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
