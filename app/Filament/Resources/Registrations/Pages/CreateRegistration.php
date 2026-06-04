<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registered_by'] ??= auth()->id();
        $data['submitted_at'] = $data['status'] === 'submitted' ? now() : null;

        if ($data['status'] === 'approved') {
            $data['reviewed_by'] = auth()->id();
            $data['reviewed_at'] = now();
            $data['submitted_at'] ??= now();
        }

        return $data;
    }
}
