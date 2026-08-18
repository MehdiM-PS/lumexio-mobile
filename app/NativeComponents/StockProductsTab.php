<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class StockProductsTab extends NativeComponent
{
    use HandlesApiErrors;

    public string $search = '';

    public string $statusFilter = 'all';

    public array $items = [];

    public bool $loading = true;

    public function mount(): void
    {
        $this->load();
    }

    public function updatedSearch(): void
    {
        $this->refresh();
    }

    /**
     * `$selected` is the chip's emitted on_change value, appended by the
     * dispatcher after the callback's own args. Each status is its own
     * independent `<chip>` (no shared native:model group exists for
     * chips), so switching statuses fires this callback twice: once
     * with `selected: true` from the tapped chip, and once with
     * `selected: false` from the previously-active chip's own echo when
     * the re-render pushes its new, deselected value back down to it.
     * Without this guard, that `false` echo would blindly reassign
     * $statusFilter back to that chip's own value, fighting the
     * just-made selection in an oscillating loop.
     */
    public function setStatusFilter(string $filter, bool $selected = true): void
    {
        if (! $selected) {
            return;
        }

        $this->statusFilter = $filter;
        $this->refresh();
    }

    /** Bound to pull-to-refresh; always bypasses the short-lived GET cache. */
    public function refresh(): void
    {
        $this->bustApiCache();
        $this->load();
    }

    private function load(): void
    {
        $this->resetApiError();
        $this->loading = true;

        $query = ['search' => $this->search, 'filter' => $this->statusFilter, 'per_page' => 50];

        $productsData = $this->callApi(fn () => app(LumexioApi::class)->get('/products', $query));
        $variantsData = $this->callApi(fn () => app(LumexioApi::class)->get(
            '/products/variants',
            ['filter' => $this->statusFilter, 'per_page' => 50],
        ));

        $products = $productsData['products'] ?? [];
        $variants = $variantsData['variants'] ?? [];

        $productIdsWithVariants = collect($variants)->pluck('product_id')->unique();

        $standaloneProducts = collect($products)
            ->reject(fn ($p) => $productIdsWithVariants->contains($p['id']))
            ->map(fn ($p) => array_merge($p, ['type' => 'product']));

        $variantsWithType = collect($variants)
            ->when($this->search !== '', fn ($c) => $c->filter(
                fn ($v) => str_contains(mb_strtolower($v['name'] ?? ''), mb_strtolower($this->search))
                    || str_contains(mb_strtolower($v['reference'] ?? ''), mb_strtolower($this->search))
            ))
            ->map(fn ($v) => array_merge($v, ['type' => 'variant']));

        $this->items = $standaloneProducts->concat($variantsWithType)
            ->sortBy('name')
            ->values()
            ->all();

        $this->loading = false;
    }

    public function select(string $type, int $id): void
    {
        $item = collect($this->items)->first(fn ($i) => $i['type'] === $type && $i['id'] === $id);

        $this->emit('select-item', $type, $id, $item);
    }

    public function render(): View
    {
        return view('native.stock-products-tab');
    }
}
