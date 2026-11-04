<?php

use App\Providers\AppServiceProvider;
use App\Providers\ApiResponseMacroServiceProvider;

return [
    AppServiceProvider::class,
    ApiResponseMacroServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
];
