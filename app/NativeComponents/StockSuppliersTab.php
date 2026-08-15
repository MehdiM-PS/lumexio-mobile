<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class StockSuppliersTab extends NativeComponent
{
    public int $activeSubTab = 0;

    public function render(): View
    {
        return view('native.stock-suppliers-tab');
    }
}
