<?php

use App\Providers\AcademicServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DormServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    AcademicServiceProvider::class,
    DormServiceProvider::class,
];
