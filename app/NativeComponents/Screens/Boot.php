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
        $this->attemptBoot();
    }

    public function retry(): void
    {
        $this->attemptBoot();
    }

    private function attemptBoot(): void
    {
        $this->resetApiError();

        $state = LocalState::current();

        if (blank($state->token)) {
            $this->replace('/login');

            return;
        }

        $user = $this->callApi(fn () => app(AuthService::class)->me());

        if ($user === null) {
            // callApi() already replaced to /login on an UnauthenticatedApiException.
            // Otherwise $lastApiError is now set (network/server failure) and the
            // view shows a retry action instead of navigating.
            return;
        }

        $this->replace('/shops/select');
    }

    public function render(): View
    {
        return view('native.boot');
    }
}
