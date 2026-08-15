<?php

namespace App\NativeComponents\Screens;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Recommendations extends NativeComponent
{
    public int $activeTab = 0;

    public function render(): View
    {
        return view('native.recommendations');
    }
}
