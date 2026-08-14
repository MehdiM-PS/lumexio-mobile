<?php

it('sets the Lumexio brand colors as the light theme', function () {
    $light = config('native-ui.theme.light');

    expect($light['primary'])->toBe('#0D9488')
        ->and($light['on-primary'])->toBe('#FFFFFF')
        ->and($light['secondary'])->toBe('#4B5563')
        ->and($light['background'])->toBe('#FFFFFF')
        ->and($light['surface'])->toBe('#FFFFFF')
        ->and($light['surface-variant'])->toBe('#F9FAFB')
        ->and($light['outline'])->toBe('#E5E7EB')
        ->and($light['destructive'])->toBe('#DC2626')
        ->and($light['accent'])->toBe('#F59E0B');
});

it('auto-derives dark mode instead of hand-writing a palette', function () {
    $dark = config('native-ui.theme.dark');

    // Dark mode is auto-derived when not explicitly set, so it should be populated
    // with luminance-inverted values from the light theme.
    expect($dark)->not()->toBeEmpty()
        ->and($dark['primary'])->toMatch('/^#[0-9A-F]{6}$/')
        ->and($dark['primary'])->not()->toBe(config('native-ui.theme.light.primary'));
});

it('sets Plus Jakarta Sans as the app-wide default font', function () {
    expect(config('native-ui.fonts.default'))->toBe('PlusJakartaSans-Regular');
});
