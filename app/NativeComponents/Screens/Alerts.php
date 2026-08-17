<?php

namespace App\NativeComponents\Screens;

use App\Models\LocalState;
use App\NativeComponents\Concerns\HandlesApiErrors;
use App\Services\LumexioApi;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class Alerts extends NativeComponent
{
    use HandlesApiErrors;

    /**
     * French label for every backend alert `type` slug, in display order.
     * Kept here (rather than trusting the currently-loaded page) because the
     * type filter must always offer the full 8-type set, even when the
     * current result page — possibly itself type-filtered — doesn't contain
     * every type.
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'stock_low' => 'Stock bas',
        'order_stuck' => 'Commande bloquée',
        'sales_drop' => 'Baisse des ventes',
        'negative_margin' => 'Marge négative',
        'seo_position_drop' => 'Baisse de position SEO',
        'supplier_order_stale' => 'Commande fournisseur en retard',
        'pattern_detected' => 'Anomalie détectée',
        'churn_spike' => 'Pic de churn',
    ];

    private const TYPE_FILTER_ALL_LABEL = 'Tous les types';

    public ?string $severityFilter = null;

    public ?string $typeFilter = null;

    public ?bool $unreadOnly = null;

    public array $alerts = [];

    public array $stats = ['total' => 0, 'unread' => 0, 'critical_unread' => 0, 'today' => 0];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->resetApiError();

        $query = array_filter([
            'severity' => $this->severityFilter,
            'type' => $this->typeFilter,
            'is_read' => $this->unreadOnly !== null ? ! $this->unreadOnly : null,
        ], fn ($v) => $v !== null);

        $alertsData = $this->callApi(fn () => app(LumexioApi::class)->get('/alerts', array_merge($query, ['per_page' => 50])));
        $this->alerts = $alertsData['alerts'] ?? [];

        $statsData = $this->callApi(fn () => app(LumexioApi::class)->get('/alerts/stats'));
        $this->stats = $statsData['stats'] ?? $this->stats;
        LocalState::current()->update(['unread_alert_count' => $this->stats['unread'] ?? 0]);
    }

    public function setSeverityFilter(?string $severity): void
    {
        $this->severityFilter = $severity;
        $this->refresh();
    }

    /**
     * The `<select>` element's `on_change` fires the label the merchant
     * picked (Select has no separate value/label pair — see
     * vendor/nativephp/mobile-ui/src/Elements/Select.php), so this maps it
     * back to the backend's `type` slug via TYPE_LABELS before refreshing.
     */
    public function setTypeFilter(string $label): void
    {
        $this->typeFilter = $label === self::TYPE_FILTER_ALL_LABEL
            ? null
            : (array_search($label, self::TYPE_LABELS, true) ?: null);

        $this->refresh();
    }

    /** @return list<string> */
    public function typeFilterOptions(): array
    {
        return [self::TYPE_FILTER_ALL_LABEL, ...array_values(self::TYPE_LABELS)];
    }

    public function typeFilterValue(): string
    {
        return $this->typeFilter !== null
            ? (self::TYPE_LABELS[$this->typeFilter] ?? self::TYPE_FILTER_ALL_LABEL)
            : self::TYPE_FILTER_ALL_LABEL;
    }

    public function toggleUnreadOnly(): void
    {
        $this->unreadOnly = $this->unreadOnly === true ? null : true;
        $this->refresh();
    }

    public function markAsRead(int $id): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/alerts/'.$id.'/read'));

        if ($result !== null) {
            $this->alerts = collect($this->alerts)
                ->map(fn ($a) => $a['id'] === $id ? array_merge($a, ['is_read' => true]) : $a)
                ->values()
                ->all();
        }
    }

    public function markAllAsRead(): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/alerts/read-all'));

        if ($result !== null) {
            $this->refresh();
        }
    }

    /**
     * Bound to the "Supprimer les alertes lues" button. This is a hard,
     * irreversible delete of every read alert for the shop (mirrors web's
     * `wire:confirm`-gated version of the same action), so it must never
     * fire from a single tap — only deleteRead() itself calls the API, and
     * only from the destructive button's callback below.
     */
    public function confirmDeleteRead(): void
    {
        Dialog::alert(
            'Supprimer les alertes lues',
            'Cette action est définitive : toutes les alertes lues seront supprimées.',
            [
                ['label' => 'Annuler', 'style' => 'cancel'],
                ['label' => 'Supprimer', 'style' => 'destructive'],
            ]
        )->buttonPressed(function (ButtonPressed $event): void {
            if ($event->label === 'Supprimer') {
                $this->deleteRead();
            }
        });
    }

    public function deleteRead(): void
    {
        $this->resetApiError();

        $result = $this->callApi(fn () => app(LumexioApi::class)->post('/alerts/delete-read'));

        if ($result !== null) {
            $this->refresh();
        }
    }

    public function render(): View
    {
        return view('native.alerts');
    }
}
