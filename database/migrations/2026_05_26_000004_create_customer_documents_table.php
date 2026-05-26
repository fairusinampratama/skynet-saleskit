<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('document_type')->default('ktp');
            $table->string('original_file_path')->nullable();
            $table->string('processed_file_path')->nullable();
            $table->longText('ocr_raw_text')->nullable();
            $table->json('ocr_parsed_data')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
