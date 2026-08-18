<?php

namespace App\NativeComponents\Screens;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Profile extends NativeComponent
{
    use HandlesApiErrors;

    public string $nameInput = '';

    public string $emailInput = '';

    public bool $saving = false;

    public function mount(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/auth/me'));

        $this->nameInput = $data['user']['name'] ?? '';
        $this->emailInput = $data['user']['email'] ?? '';
    }

    public function save(): void
    {
        $this->resetApiError();
        $this->saving = true;

        $data = $this->callApi(fn () => app(LumexioApi::class)->patch('/auth/profile', [
            'name' => $this->nameInput,
            'email' => $this->emailInput,
        ]));

        if ($data !== null) {
            $this->nameInput = $data['user']['name'] ?? $this->nameInput;
            $this->emailInput = $data['user']['email'] ?? $this->emailInput;
        }

        $this->saving = false;
    }

    public function accountInitialsFor(string $name): string
    {
        $parts = array_filter(explode(' ', trim($name)));

        return mb_strtoupper(collect($parts)->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
    }

    public function render(): View
    {
        return view('native.profile');
    }
}
