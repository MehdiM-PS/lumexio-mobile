<?php

namespace App\NativeComponents\Concerns;

use App\Models\LocalState;
use App\Services\AuthService;
use App\Services\LumexioApi;
use Native\Mobile\Edge\Element;
use Native\Mobile\Facades\Browser;

/**
 * Shared tab-bar header behavior — the shop-switcher and account bottom
 * sheets, their supporting data, and the header's navigation actions.
 * Mixed into all 5 tab screens (Dashboard, Forecasts, Sales,
 * Recommendations, Alerts) so `TabsLayout::navBar()`'s shared header
 * partial can dispatch its `@press` callbacks against whichever screen
 * is currently rendered, and so every screen's account-sheet partial
 * has the same name/email/initials without each screen redefining it.
 *
 * Requires the host screen to also `use HandlesApiErrors` (for
 * `callApi()`/`resetApiError()`), matching every existing screen in
 * this app. Host screens call `$this->loadCurrentUser()` from their own
 * `refresh()`/`mount()` — this trait doesn't call it automatically,
 * since some screens fetch it as part of a larger batched refresh.
 */
trait HasHeaderChrome
{
    public bool $shopSheetOpen = false;

    public bool $accountSheetOpen = false;

    public array $switcherShops = [];

    public ?array $currentUser = null;

    /**
     * The shop-switcher pill for `TabsLayout::navBar()`'s `->titleView()`.
     * Blade's `class="..."` attributes can't express `min-width` — Tailwind
     * parsing has no handler for it, so `NativeElementCollector::applyLayout`
     * never calls `Element::minWidth()`. Only the fluent PHP API can, and
     * that's the only lever available (from this app's side) that gets
     * `native/partials/header.blade.php`'s pill left-aligned in the nav
     * bar's `.principal` slot on iOS: `NodeLayoutModifier` reports the
     * `.frame`'s ideal width as the pill's own natural (short, hugging)
     * size when width isn't forced, and SwiftUI centers a `.principal`
     * toolbar item using exactly that ideal size — so a short shop name
     * renders centered no matter how the wrapping row's own Tailwind
     * classes are set. Forcing a floor via `->minWidth()` here is what
     * makes SwiftUI allocate (and then left-align within) a wider region
     * regardless of content length.
     *
     * `fromViewPartial()` (protected on `NativeComponent`) is reachable
     * from here because this trait is mixed directly into a
     * `NativeComponent` subclass — traits share the host class's
     * protected/private access as if the method were declared in the
     * class body.
     */
    public function headerTitleView(): Element
    {
        return $this->fromViewPartial(view('native.partials.header'))->minWidth(260);
    }

    public function openShopSwitcher(): void
    {
        $this->shopSheetOpen = true;
        $this->accountSheetOpen = false;
        $this->loadSwitcherShops();
    }

    public function closeShopSwitcher(): void
    {
        $this->shopSheetOpen = false;
    }

    public function openAccountSheet(): void
    {
        $this->accountSheetOpen = true;
        $this->shopSheetOpen = false;
    }

    public function closeAccountSheet(): void
    {
        $this->accountSheetOpen = false;
    }

    public function goAlerts(): void
    {
        $this->closeAccountSheet();
        $this->navigate('/alerts');
    }

    public function goProfile(): void
    {
        $this->closeAccountSheet();
        $this->navigate('/profile');
    }

    /**
     * Switches the active shop and force-remounts on `/dashboard`.
     * `NativeComponent` has no "current route" accessor to generalize
     * this to "stay on whichever of the 5 tabs the sheet was opened
     * from" — matches `SelectShop::select()`'s existing behavior.
     */
    public function selectShop(string $shopId): void
    {
        $state = LocalState::current();
        $shop = collect($this->switcherShops)->firstWhere('id', $shopId);

        $state->update([
            'shop_id' => $shopId,
            // Falls back to the previously-cached name rather than blanking
            // the header when the shop can't be resolved — same convention
            // Dashboard::refresh() uses for this field.
            'active_shop_name' => $shop['name'] ?? $state->active_shop_name,
        ]);

        $this->closeShopSwitcher();
        $this->replace('/dashboard');
    }

    /**
     * The shop-switcher sheet's "+ Ajouter une boutique" row. There's no
     * native shop-creation flow to build, so this opens the marketing site
     * in the system browser instead — a real, functional destination, same
     * precedent as Login::openSignup() for the mockup's own inert "Essai
     * gratuit 30 jours" CTA.
     */
    public function openAddShop(): void
    {
        Browser::open(config('lumexio.asset_url'));
    }

    public function currentShopId(): ?string
    {
        return LocalState::current()->shop_id;
    }

    public function loadCurrentUser(): void
    {
        $this->currentUser = $this->callApi(fn () => ['user' => app(AuthService::class)->me()])['user'] ?? null;
    }

    public function accountName(): string
    {
        return $this->currentUser['name'] ?? '';
    }

    public function accountEmail(): string
    {
        return $this->currentUser['email'] ?? '';
    }

    public function accountInitials(): string
    {
        $parts = array_filter(explode(' ', trim($this->accountName())));

        return mb_strtoupper(collect($parts)->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
    }

    public function logout(): void
    {
        app(AuthService::class)->logout();
        $this->replace('/login');
    }

    private function loadSwitcherShops(): void
    {
        $this->resetApiError();

        $data = $this->callApi(fn () => app(LumexioApi::class)->get('/shops'));

        $this->switcherShops = $data['shops'] ?? [];
    }
}
