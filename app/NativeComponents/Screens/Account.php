<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Account extends NativeComponent
{
    use HandlesApiErrors;

    public string $currentPasswordInput = '';

    public string $newPasswordInput = '';

    public string $newPasswordConfirmationInput = '';

    public bool $saving = false;

    public function mount(): void
    {
        $this->resetApiError();
    }

    public function changePassword(): void
    {
        $this->resetApiError();
        $this->saving = true;

        $data = $this->callApi(fn () => app(LumexioApi::class)->patch('/auth/password', [
            'current_password' => $this->currentPasswordInput,
            'password' => $this->newPasswordInput,
            'password_confirmation' => $this->newPasswordConfirmationInput,
        ]));

        if ($data !== null) {
            $this->currentPasswordInput = '';
            $this->newPasswordInput = '';
            $this->newPasswordConfirmationInput = '';
        }

        $this->saving = false;
    }

    public function appVersion(): string
    {
        return config('nativephp.version', '—');
    }

    public function render(): View
    {
        return view('native.account');
    }
}
