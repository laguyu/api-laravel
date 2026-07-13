<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\RepositoryServiceProvider;
use Illuminate\View\ViewServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    PaymentServiceProvider::class,
    ViewServiceProvider::class,
];
