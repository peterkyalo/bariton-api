<?php

namespace App\Providers;

use App\Services\Receipts\ReceiptIssuerInterface;
use App\Services\Receipts\StandardPdfReceiptIssuer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            ReceiptIssuerInterface::class,
            StandardPdfReceiptIssuer::class
        );

        $this->app->bind(
            \App\Services\Receipts\CreditNoteIssuerInterface::class,
            \App\Services\Receipts\StandardPdfCreditNoteIssuer::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
