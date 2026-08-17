<?php

it('sets the Lumexio brand colors as the light theme', function () {
    $light = config('native-ui.theme.light');

    expect($light['primary'])->toBe('#2D5D5A')
        ->and($light['on-primary'])->toBe('#FFFFFF')
        ->and($light['secondary'])->toBe('#6D6F78')
        ->and($light['background'])->toBe('#FAFAF8')
        ->and($light['surface'])->toBe('#FFFFFF')
        ->and($light['surface-variant'])->toBe('#F0EDE8')
        ->and($light['outline'])->toBe('#14111111') // wire format: AARRGGBB, from authored CSS #11111114
        ->and($light['destructive'])->toBe('#E24947')
        ->and($light['accent'])->toBe('#EC7C0E')
        ->and($light['success'])->toBe('#36A558')
        ->and($light['on-success'])->toBe('#FFFFFF');
});

it('auto-derives dark mode instead of hand-writing a palette', function () {
    $dark = config('native-ui.theme.dark');

    // Dark mode is auto-derived when not explicitly set, so it should be populated
    // with luminance-inverted values from the light theme.
    expect($dark)->not()->toBeEmpty()
        ->and($dark['primary'])->toMatch('/^#[0-9A-F]{6}$/')
        ->and($dark['primary'])->not()->toBe(config('native-ui.theme.light.primary'));
});

it('has no bundled app-wide default font, falling back to the system font', function () {
    expect(config('native-ui.fonts.default'))->toBeNull();
});
