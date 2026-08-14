<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\AuthService;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Login extends NativeComponent
{
    use HandlesApiErrors;

    public string $email = '';

    public string $password = '';

    public bool $loading = false;

    public function submit(): void
    {
        $this->loading = true;
        $this->resetApiError();

        $user = $this->callApi(fn () => app(AuthService::class)->login(
            $this->email,
            $this->password,
            'Lumexio iOS',
        ));

        $this->loading = false;

        if ($user !== null) {
            $this->replace('/shops/select');
        }
    }

    public function render(): View
    {
        return view('native.login');
    }
}
