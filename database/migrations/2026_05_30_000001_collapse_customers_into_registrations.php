<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registrations')) {
            $this->addRegistrationColumn('name', fn (Blueprint $table) => $table->string('name')->default('Draft customer'));
            $this->addRegistrationColumn('nik', fn (Blueprint $table) => $table->string('nik', 32)->nullable()->index());
            $this->addRegistrationColumn('phone', fn (Blueprint $table) => $table->string('phone')->default('-'));
            $this->addRegistrationColumn('ktp_full_address', fn (Blueprint $table) => $table->text('ktp_full_address')->nullable());
            $this->addRegistrationColumn('installation_full_address', fn (Blueprint $table) => $table->text('installation_full_address')->nullable());
            $this->addRegistrationColumn('latitude', fn (Blueprint $table) => $table->decimal('latitude', 10, 8)->nullable());
            $this->addRegistrationColumn('longitude', fn (Blueprint $table) => $table->decimal('longitude', 11, 8)->nullable());
            $this->addRegistrationColumn('ktp_original_file_path', fn (Blueprint $table) => $table->string('ktp_original_file_path')->nullable());
            $this->addRegistrationColumn('ktp_processed_file_path', fn (Blueprint $table) => $table->string('ktp_processed_file_path')->nullable());
            $this->addRegistrationColumn('ktp_ocr_raw_text', fn (Blueprint $table) => $table->longText('ktp_ocr_raw_text')->nullable());
            $this->addRegistrationColumn('ktp_ocr_parsed_data', fn (Blueprint $table) => $table->json('ktp_ocr_parsed_data')->nullable());
            $this->addRegistrationColumn('ktp_verified_at', fn (Blueprint $table) => $table->timestamp('ktp_verified_at')->nullable());
            $this->addRegistrationColumn('package', fn (Blueprint $table) => $table->string('package')->nullable());

            $this->copyLegacyCustomerData();
            $this->dropRegistrationCustomerId();
            $this->dropRegistrationEbillingColumns();
        }

        $this->dropAreaEbillingColumns();
        $this->dropTaskCustomerId();

        Schema::dropIfExists('ebilling_sync_logs');
        Schema::dropIfExists('ebilling_packages');
        Schema::dropIfExists('customer_documents');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }

    public function down(): void
    {
        //
    }

    private function addRegistrationColumn(string $column, Closure $definition): void
    {
        if (Schema::hasColumn('registrations', $column)) {
            return;
        }

        Schema::table('registrations', fn (Blueprint $table) => $definition($table));
    }

    private function copyLegacyCustomerData(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasColumn('registrations', 'customer_id')) {
            return;
        }

        DB::table('registrations')
            ->join('customers', 'registrations.customer_id', '=', 'customers.id')
            ->select([
                'registrations.id',
                'customers.name',
                'customers.nik',
                'customers.phone',
                'customers.full_address',
                'customers.latitude',
                'customers.longitude',
            ])
            ->orderBy('registrations.id')
            ->each(function (object $row): void {
                DB::table('registrations')->where('id', $row->id)->update([
                    'name' => $row->name ?: 'Draft customer',
                    'nik' => $row->nik,
                    'phone' => $row->phone ?: '-',
                    'installation_full_address' => $row->full_address,
                    'latitude' => $row->latitude,
                    'longitude' => $row->longitude,
                ]);
            });

        if (Schema::hasTable('customer_addresses')) {
            DB::table('customer_addresses')
                ->join('registrations', 'customer_addresses.customer_id', '=', 'registrations.customer_id')
                ->select([
                    'registrations.id',
                    'customer_addresses.address_type',
                    'customer_addresses.full_address',
                    'customer_addresses.latitude',
                    'customer_addresses.longitude',
                ])
                ->orderBy('registrations.id')
                ->each(function (object $row): void {
                    $payload = [];

                    if ($row->address_type === 'ktp') {
                        $payload['ktp_full_address'] = $row->full_address;
                    }

                    if ($row->address_type === 'installation') {
                        $payload['installation_full_address'] = $row->full_address;
                        $payload['latitude'] = $row->latitude;
                        $payload['longitude'] = $row->longitude;
                    }

                    DB::table('registrations')->where('id', $row->id)->update($payload);
                });
        }

        if (Schema::hasTable('customer_documents')) {
            DB::table('customer_documents')
                ->join('registrations', 'customer_documents.customer_id', '=', 'registrations.customer_id')
                ->where('customer_documents.document_type', 'ktp')
                ->select([
                    'registrations.id',
                    'customer_documents.original_file_path',
                    'customer_documents.processed_file_path',
                    'customer_documents.ocr_raw_text',
                    'customer_documents.ocr_parsed_data',
                    'customer_documents.verified_at',
                ])
                ->orderBy('registrations.id')
                ->each(function (object $row): void {
                    DB::table('registrations')->where('id', $row->id)->update([
                        'ktp_original_file_path' => $row->original_file_path,
                        'ktp_processed_file_path' => $row->processed_file_path,
                        'ktp_ocr_raw_text' => $row->ocr_raw_text,
                        'ktp_ocr_parsed_data' => $row->ocr_parsed_data,
                        'ktp_verified_at' => $row->verified_at,
                    ]);
                });
        }
    }

    private function dropRegistrationCustomerId(): void
    {
        if (! Schema::hasColumn('registrations', 'customer_id')) {
            return;
        }

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });
    }

    private function dropTaskCustomerId(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        if (! Schema::hasColumn('tasks', 'registration_id')) {
            Schema::table('tasks', fn (Blueprint $table) => $table->unsignedBigInteger('registration_id')->nullable()->index());
        }

        if (! Schema::hasColumn('tasks', 'customer_id')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });
    }

    private function dropRegistrationEbillingColumns(): void
    {
        $columns = [
            'ebilling_customer_id',
            'ebilling_package_id',
            'pppoe_user',
            'ebilling_status',
            'synced_at',
        ];

        if (Schema::hasColumn('registrations', 'ebilling_package_id')) {
            Schema::table('registrations', function (Blueprint $table): void {
                $table->dropForeign(['ebilling_package_id']);
            });
        }

        if (Schema::hasColumn('registrations', 'pppoe_user')) {
            Schema::table('registrations', function (Blueprint $table): void {
                $table->dropUnique(['pppoe_user']);
            });
        }

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn('registrations', $column),
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table('registrations', function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }

    private function dropAreaEbillingColumns(): void
    {
        if (! Schema::hasTable('areas')) {
            return;
        }

        $columns = array_values(array_filter(
            ['ebilling_area_code', 'ebilling_area_id'],
            fn (string $column): bool => Schema::hasColumn('areas', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('areas', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
