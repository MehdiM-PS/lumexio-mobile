<?php

namespace App\NativeComponents\Screens;

use App\Models\LocalState;
use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class SelectShop extends NativeComponent
{
    use HandlesApiErrors;

    public array $shops = [];

    public function mount(): void
    {
        $this->fetchShops();
    }

    /** Explicit user action after a failed/empty fetch; always bypasses the short-lived GET cache. */
    public function retry(): void
    {
        $this->bustApiCache();
        $this->fetchShops();
    }

    /** Bound to pull-to-refresh; always bypasses the short-lived GET cache. */
    public function refresh(): void
    {
        $this->bustApiCache();
        $this->fetchShops();
    }

    private function fetchShops(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/shops'));

        $this->shops = $data['shops'] ?? [];

        $currentShopId = LocalState::current()->shop_id;

        if (filled($currentShopId) && collect($this->shops)->contains('id', $currentShopId)) {
            $this->select($currentShopId);

            return;
        }

        if (count($this->shops) === 1) {
            $this->select($this->shops[0]['id']);
        }
    }

    public function select(string $shopId): void
    {
        LocalState::current()->update(['shop_id' => $shopId]);

        $this->replace('/dashboard');
    }

    public function render(): View
    {
        return view('native.select-shop');
    }
}
