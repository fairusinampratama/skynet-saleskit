<?php

namespace App\Jobs;

use App\Contracts\EbillingClient;
use App\Models\EbillingSyncLog;
use App\Models\Registration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncRegistrationToEbilling implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $registrationId,
        public ?int $syncedBy = null,
    ) {}

    public function handle(EbillingClient $client): void
    {
        $registration = Registration::with(['customer.installationAddress', 'customer.ktpDocument', 'area'])
            ->findOrFail($this->registrationId);

        $payload = $this->payloadFor($registration);

        $log = EbillingSyncLog::create([
            'registration_id' => $registration->id,
            'synced_by' => $this->syncedBy,
            'status' => 'pending',
            'request_payload' => $payload,
            'started_at' => now(),
        ]);

        try {
            $registration->update(['status' => Registration::STATUS_SYNCING]);

            $result = $client->createCustomerFromRegistration($registration);

            $registration->customer->update([
                'ebilling_customer_id' => $result['customer_id'],
            ]);

            $registration->update([
                'status' => Registration::STATUS_SYNCED,
                'synced_at' => now(),
            ]);

            $log->update([
                'status' => 'success',
                'response_payload' => $result['response'],
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $registration->update(['status' => Registration::STATUS_SYNC_FAILED]);

            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function payloadFor(Registration $registration): array
    {
        $customer = $registration->customer;
        $address = $customer->installationAddress;

        return [
            'customer' => [
                'name' => $customer->name,
                'nik' => $customer->nik,
                'phone' => $customer->phone,
                'email' => $customer->email,
            ],
            'area' => [
                'code' => $registration->area?->code,
                'ebilling_area_code' => $registration->area?->ebilling_area_code,
            ],
            'installation_address' => [
                'full_address' => $address?->full_address,
                'province' => $address?->province,
                'city' => $address?->city,
                'district' => $address?->district,
                'village' => $address?->village,
                'rt' => $address?->rt,
                'rw' => $address?->rw,
                'postal_code' => $address?->postal_code,
                'latitude' => $address?->latitude,
                'longitude' => $address?->longitude,
            ],
        ];
    }
}
