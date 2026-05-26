<?php

namespace App\Services;

use App\Contracts\EbillingClient;
use App\Models\Registration;

class MockEbillingClient implements EbillingClient
{
    public function createCustomerFromRegistration(Registration $registration): array
    {
        $registration->loadMissing(['customer', 'area', 'customer.installationAddress', 'customer.ktpDocument']);

        return [
            'customer_id' => 'MOCK-'.now()->format('YmdHis').'-'.$registration->id,
            'response' => [
                'status' => 'accepted',
                'message' => 'Mock eBilling customer creation completed.',
                'registration_id' => $registration->id,
                'area_code' => $registration->area?->ebilling_area_code,
            ],
        ];
    }
}
