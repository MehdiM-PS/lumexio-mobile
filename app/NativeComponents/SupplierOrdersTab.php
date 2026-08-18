<?php

namespace App\NativeComponents;

use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class SupplierOrdersTab extends NativeComponent
{
    use HandlesApiErrors;

    private const STATUS_FILTER_ALL_LABEL = 'Tous les statuts';

    private const STATUS_LABELS = [
        'brouillon' => 'Brouillon',
        'commandee' => 'Commandée',
        'en_cours' => 'En cours',
        'recue_partielle' => 'Reçue partiellement',
        'recue' => 'Reçue',
        'annulee' => 'Annulée',
    ];

    public string $search = '';

    public ?string $statusFilter = null;

    public bool $hideCompleted = true;

    public array $orders = [];

    public array $stats = ['total' => 0, 'pending' => 0, 'pending_value' => 0, 'received_month' => 0];

    public int $currentPage = 1;

    public bool $hasMorePages = false;

    public function mount(): void
    {
        $this->load();
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
        $this->currentPage = 1;

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/supplier-orders', array_filter([
            'search' => $this->search ?: null,
            'status' => $this->statusFilter,
            'hide_completed' => $this->hideCompleted,
            'page' => 1,
        ], fn ($v) => $v !== null)));

        $this->orders = $data['orders']['data'] ?? [];
        $this->stats = $data['stats'] ?? $this->stats;
        $this->hasMorePages = ($data['orders']['pagination']['current_page'] ?? 1) < ($data['orders']['pagination']['last_page'] ?? 1);
    }

    public function loadMore(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/supplier-orders', array_filter([
            'search' => $this->search ?: null,
            'status' => $this->statusFilter,
            'hide_completed' => $this->hideCompleted,
            'page' => $this->currentPage + 1,
        ], fn ($v) => $v !== null)));

        if ($data !== null) {
            $this->orders = array_merge($this->orders, $data['orders']['data'] ?? []);
            $this->currentPage = $data['orders']['pagination']['current_page'] ?? $this->currentPage + 1;
            $this->hasMorePages = $this->currentPage < ($data['orders']['pagination']['last_page'] ?? $this->currentPage);
        }
    }

    public function updatedSearch(): void
    {
        $this->refresh();
    }

    /**
     * The `<select>` element's `on_change` fires the label the merchant picked
     * (Select has no separate value/label pair), so this maps it back to the
     * backend's status slug via STATUS_LABELS before refreshing — mirrors
     * Alerts.php's established setTypeFilter() pattern exactly.
     */
    public function setStatusFilter(string $label): void
    {
        $this->statusFilter = $label === self::STATUS_FILTER_ALL_LABEL
            ? null
            : (array_search($label, self::STATUS_LABELS, true) ?: null);

        $this->refresh();
    }

    /** @return list<string> */
    public function statusFilterOptions(): array
    {
        return [self::STATUS_FILTER_ALL_LABEL, ...array_values(self::STATUS_LABELS)];
    }

    public function statusFilterValue(): string
    {
        return $this->statusFilter !== null
            ? (self::STATUS_LABELS[$this->statusFilter] ?? self::STATUS_FILTER_ALL_LABEL)
            : self::STATUS_FILTER_ALL_LABEL;
    }

    public function toggleHideCompleted(): void
    {
        $this->hideCompleted = ! $this->hideCompleted;
        $this->refresh();
    }

    public function select(string $id): void
    {
        $order = collect($this->orders)->firstWhere('id', $id) ?? ['id' => $id];

        $this->navigate('/supplier-orders/'.$id, ['order' => $order]);
    }

    public function render(): View
    {
        return view('native.supplier-orders-tab');
    }
}
