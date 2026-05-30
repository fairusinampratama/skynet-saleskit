<?php

namespace App\Filament\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();

            redirect()->intended($this->getRedirectUrlForUser($user));
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider();
        $credentials = $this->getCredentialsFromFormData($data);
        $user = $authProvider->retrieveByCredentials($credentials);

        if (! $authGuard->attempt($credentials, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Nama Pengguna')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata Sandi')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required();
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Ingat saya');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Masuk';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Masuk';
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    private function getRedirectUrlForUser(?Authenticatable $user): string
    {
        return $user?->role === 'admin'
            ? Filament::getUrl()
            : route('technician.registrations.index');
    }
}
