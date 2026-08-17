<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\NativeComponents\Concerns\HasHeaderChrome;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Placeholder for the Ventes (Sales) tab — full content is a later slice
 * (mostly backed by the existing, mobile-unused RevenueController). This
 * stands up the 5th tab so the bar isn't broken between slices.
 */
class Sales extends NativeComponent
{
    use HandlesApiErrors;
    use HasHeaderChrome;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();
        $this->loadCurrentUser();
    }

    public function render(): View
    {
        return view('native.sales');
    }
}
