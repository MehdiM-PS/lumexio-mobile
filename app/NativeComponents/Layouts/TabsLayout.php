<?php

namespace App\NativeComponents\Layouts;

use App\Icons\AndroidOutlined;
use App\Icons\Ios;
use App\Models\LocalState;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

/**
 * Requires every screen this layout is attached to (`Route::nativeGroup`)
 * to `use HasHeaderChrome` — `navBar()` calls `$screen->headerTitleView()`,
 * which only exists on that trait. All 7 screens under the `TabsLayout`
 * group in `routes/web.php` already do; a screen without it would hit a
 * fatal `Error` here rather than degrading, since the trait method isn't
 * declared on `NativeComponent` itself.
 */
class TabsLayout extends NativeLayout
{
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        $unreadAlertCount = LocalState::current()->unread_alert_count;

        $alertsTab = Tab::link('Alertes', '/alerts', icon: 'bell');
        if ($unreadAlertCount > 0) {
            $alertsTab->badge((string) $unreadAlertCount);
        }

        return TabBar::make()
            ->activeColor(theme('primary'))
            ->add(Tab::link('Accueil', '/dashboard', icon: 'house'))
            ->add(Tab::link('Prévisions', '/forecasts', icon: 'chart.line.uptrend.xyaxis'))
            ->add(Tab::link('Ventes', '/sales', ios: Ios::Cart, android: AndroidOutlined::ShoppingCart))
            ->add(Tab::link('Reco', '/recommendations', icon: 'lightbulb'))
            ->add($alertsTab);
    }

    /**
     * The shop-switcher pill lives in the bar's `.principal` slot
     * (titleView) — see `HasHeaderChrome::headerTitleView()` for why that
     * view is built via the fluent Element API (chaining `->minWidth()`)
     * instead of passed as a plain Blade view: it's the only way to keep
     * the pill pinned to the leading edge on iOS regardless of shop-name
     * length. The account/alerts icon buttons used to be crammed into
     * that same slot alongside it (a `w-full justify-between` row
     * fighting for space it doesn't have, which is what made the header
     * look cramped) — they now render as real trailing bar actions,
     * which get guaranteed, non-squeezed space of their own. The alert
     * count moved to the bottom tab bar's badge (see tabBar() above)
     * since a NavAction button has no badge slot of its own.
     */
    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()
            ->titleView($screen->headerTitleView())
            ->action(
                NavAction::make('account')
                    ->icon('person')
                    ->a11yLabel('Mon compte')
                    ->press('openAccountSheet')
            )
            ->action(
                NavAction::make('alerts')
                    ->icon('bell')
                    ->a11yLabel('Alertes')
                    ->press('goAlerts')
            );
    }

    public function usesNativeChrome(): bool
    {
        return true;
    }
}
