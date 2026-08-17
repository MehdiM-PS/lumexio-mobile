<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Recommendations extends NativeComponent
{
    use HandlesApiErrors;
    use HasHeaderChrome;

    public int $activeTab = 0;

    public function mount(): void
    {
        $this->resetApiError();
        $this->loadCurrentUser();
    }

    public function render(): View
    {
        return view('native.recommendations');
    }
}
