<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('nik', 32)->nullable()->after('name')->index();
            $table->string('status')->default('active')->after('email');
            $table->string('ebilling_customer_id')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['nik', 'status', 'ebilling_customer_id']);
        });
    }
};
