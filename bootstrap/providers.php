<?php

use App\Providers\AcademicServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DormServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GamificationServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AcademicServiceProvider::class,
    DormServiceProvider::class,
    GamificationServiceProvider::class,
];
