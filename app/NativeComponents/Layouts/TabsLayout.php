<?php

namespace App\NativeComponents\Layouts;

use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

class TabsLayout extends NativeLayout
{
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->activeColor(theme('primary'))
            ->add(Tab::link('Accueil', '/dashboard', icon: 'house'))
            ->add(Tab::link('Prévisions', '/forecasts', icon: 'chart.line.uptrend.xyaxis'))
            ->add(Tab::link('Ventes', '/sales', icon: 'eurosign.circle'))
            ->add(Tab::link('Reco', '/recommendations', icon: 'lightbulb'))
            ->add(Tab::link('Alertes', '/alerts', icon: 'bell'));
    }

    public function navBar(NativeComponent $screen): ?NavBar
    {
        return NavBar::make()->titleView(view('native.partials.header'));
    }

    public function usesNativeChrome(): bool
    {
        return true;
    }
}
