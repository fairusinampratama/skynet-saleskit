<?php

namespace App\Providers;

use App\Contracts\EbillingClient;
use App\Contracts\OcrService;
use App\Http\Responses\LoginResponse;
use App\Services\MockEbillingClient;
use App\Services\TesseractOcrService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EbillingClient::class, MockEbillingClient::class);
        $this->app->bind(OcrService::class, TesseractOcrService::class);
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
