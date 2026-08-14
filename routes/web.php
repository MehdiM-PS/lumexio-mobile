<?php

use App\NativeComponents\Screens\Boot;
use App\NativeComponents\Screens\Login;
use Illuminate\Support\Facades\Route;

Route::native('/', Boot::class);
Route::native('/login', Login::class);
