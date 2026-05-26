<?php

namespace App\Contracts;

use App\Models\Registration;

interface EbillingClient
{
    /**
     * @return array{customer_id: string, response: array<string, mixed>}
     */
    public function createCustomerFromRegistration(Registration $registration): array;
}
