<?php

/**
 * Native UI — Theme Tokens
 *
 * Published via `php artisan vendor:publish --tag=native-ui-config`.
 * Edit to customize your app's visual identity in one place.
 *
 * For dynamic per-tenant theming, use Nativephp\NativeUi\Theme::merge([...])
 * from a service provider. Runtime merges deep-merge on top of these values.
 *
 * Decision log: /docs/NATIVE-UI-REWRITE-PLAN.md (D — theme layer)
 */

return [

    /*
    |---------------------------------------------------------------------------
    | Theme
    |---------------------------------------------------------------------------
    |
    | 17 color tokens, 4 radii, 4 font sizes, font family.
    |
    | "on-X" means "color of content placed ON a surface of color X"
    |   — i.e., text/icons on that background.
    |
    | Color tokens accept:
    |   - CSS hex: '#B91C1C', '#F00', or with alpha '#8B5CF680' (#RRGGBBAA)
    |   - Tailwind palette names: 'red-300', 'orange-800'
    |   - Opacity modifiers on either: 'red-300/20', '#8B5CF6/50'
    |
    | Dark mode is auto-derived from `light` when `dark` is not set. To opt
    | into explicit dark tokens, fill out the `dark` block.
    |
    | The default pairs meet WCAG AA (4.5:1) — if you customize, keep each
    | `on-*` color at 4.5:1 contrast against its background token.
    |
    */

    'theme' => [

        'light' => [
            'primary' => '#2D5D5A',
            'on-primary' => '#FFFFFF',

            'secondary' => '#6D6F78',
            'on-secondary' => '#FFFFFF',

            'surface' => '#FFFFFF',
            'on-surface' => '#14151A',
            'background' => '#FAFAF8',
            'on-background' => '#14151A',

            'surface-variant' => '#F0EDE8',
            'on-surface-variant' => '#6D6F78',

            'outline' => '#11111114',

            'destructive' => '#E24947',
            'on-destructive' => '#FFFFFF',

            'accent' => '#EC7C0E',
            'on-accent' => '#FFFFFF',

            // Positive/success indicator — deltas, confirmations. Distinct
            // from `primary` (brand teal) per the 2026-08 redesign, which
            // uses a dedicated green for "up"/"good" signals.
            'success' => '#36A558',
            'on-success' => '#FFFFFF',
        ],

        'dark' => [
            // Left empty on purpose — auto-derived from `light` by luminance inversion.
        ],

        // Corner radii (points / dp).
        'radius-sm' => 6,
        'radius-md' => 10,
        'radius-lg' => 14,
        'radius-full' => 9999,

        // Font size scale (points / sp).
        'font-sm' => 14,
        'font-md' => 16,
        'font-lg' => 20,
        'font-xl' => 24,
    ],

    'fonts' => [
        // System font (-apple-system / SF Pro) per the 2026-08 redesign —
        // no bundled default typeface.
    ],

];
