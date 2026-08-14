<?php

namespace App\NativeComponents\Screens;

use App\Models\LocalState;
use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\AuthService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Boot extends NativeComponent
{
    use HandlesApiErrors;

    public function mount(): void
    {
        $state = LocalState::current();

        if (blank($state->token)) {
            $this->replace('/login');

            return;
        }

        $user = $this->callApi(fn () => app(AuthService::class)->me());

        if ($user === null) {
            // callApi() already replaced to /login on an UnauthenticatedApiException.
            return;
        }

        $this->replace(blank($state->shop_id) ? '/shops/select' : '/dashboard');
    }

    public function render(): View
    {
        return view('native.boot');
    }
}
