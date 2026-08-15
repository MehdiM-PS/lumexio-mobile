<?php

use App\NativeComponents\Layouts\TabsLayout;
use App\NativeComponents\Screens\Boot;
use App\NativeComponents\Screens\Dashboard;
use App\NativeComponents\Screens\ItemDetail;
use App\NativeComponents\Screens\Login;
use App\NativeComponents\Screens\OrderDetail;
use App\NativeComponents\Screens\Orders;
use App\NativeComponents\Screens\Recommendations;
use App\NativeComponents\Screens\SelectShop;
use App\NativeComponents\Screens\Stock;
use Illuminate\Support\Facades\Route;

Route::native('/', Boot::class);
Route::native('/login', Login::class);
Route::native('/shops/select', SelectShop::class);
Route::native('/stock/item/{type}/{id}', ItemDetail::class);
Route::native('/orders/{id}', OrderDetail::class);

Route::nativeGroup(TabsLayout::class, function () {
    Route::native('/dashboard', Dashboard::class);
    Route::native('/stock', Stock::class);
    Route::native('/orders', Orders::class);
    Route::native('/recommendations', Recommendations::class);
});
