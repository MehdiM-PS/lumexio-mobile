<?php

namespace App\NativeComponents\Layouts;

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
            ->add(Tab::link('Dashboard', '/dashboard', icon: 'home'))
            ->add(Tab::link('Stock', '/stock', icon: 'shippingbox'))
            ->add(Tab::link('Commandes', '/orders', icon: 'bag'))
            ->add(Tab::link('Recommandations', '/recommendations', icon: 'lightbulb'))
            ->add(Tab::link('Alertes', '/alerts', icon: 'bell'));
    }

    public function usesNativeChrome(): bool
    {
        return true;
    }
}
