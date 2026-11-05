<?php

namespace App\Providers;

use App\Contracts\TicketPdfGenerator;
use App\Services\Documents\DompdfTicketPdfGenerator;
use Illuminate\Support\ServiceProvider;

class DocumentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketPdfGenerator::class, DompdfTicketPdfGenerator::class);
    }
}
