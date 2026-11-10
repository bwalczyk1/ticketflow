<?php

use App\Providers\AppServiceProvider;
use App\Providers\DocumentsServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PaymentsServiceProvider;

return [
    AppServiceProvider::class,
    DocumentsServiceProvider::class,
    HorizonServiceProvider::class,
    PaymentsServiceProvider::class,
];
