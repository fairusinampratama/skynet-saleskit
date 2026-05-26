<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $user = Filament::auth()->user();

        if ($user?->role === 'admin') {
            return redirect()->intended(Filament::getUrl());
        }

        return redirect()->route('technician.registrations.index');
    }
}
