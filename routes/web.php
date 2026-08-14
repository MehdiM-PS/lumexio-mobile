<?php

use App\NativeComponents\Screens\Boot;
use App\NativeComponents\Screens\Login;
use App\NativeComponents\Screens\SelectShop;
use Illuminate\Support\Facades\Route;

Route::native('/', Boot::class);
Route::native('/login', Login::class);
Route::native('/shops/select', SelectShop::class);
