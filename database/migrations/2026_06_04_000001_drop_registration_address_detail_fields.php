<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $columns = [
        'email',
        'province',
        'city',
        'district',
        'village',
        'rt',
        'rw',
        'postal_code',
    ];

    public function up(): void
    {
        $existing = array_values(array_filter(
            $this->columns,
            fn (string $column): bool => Schema::hasColumn('registrations', $column),
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('registrations', function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        $missing = array_values(array_filter(
            $this->columns,
            fn (string $column): bool => ! Schema::hasColumn('registrations', $column),
        ));

        if ($missing === []) {
            return;
        }

        Schema::table('registrations', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $column) {
                $table->string($column)->nullable();
            }
        });
    }
};
