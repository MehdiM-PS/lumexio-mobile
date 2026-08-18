<?php

use App\NativeComponents\Screens\Account;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('changes the password and clears the form on success', function () {
    Http::fake(['*/api/v1/auth/password' => Http::response(['message' => 'Mot de passe mis à jour'])]);

    $screen = Native::test(Account::class);
    $screen->set('currentPasswordInput', 'old-pass')
        ->set('newPasswordInput', 'new-pass-123')
        ->set('newPasswordConfirmationInput', 'new-pass-123')
        ->call('changePassword');

    expect($screen->get('lastApiError'))->toBeNull();
    expect($screen->get('currentPasswordInput'))->toBe('');
    expect($screen->get('newPasswordInput'))->toBe('');
    expect($screen->get('newPasswordConfirmationInput'))->toBe('');
});

it('surfaces a server error and keeps the form filled', function () {
    Http::fake(['*/api/v1/auth/password' => Http::response([
        'message' => 'validation error',
        'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
    ], 422)]);

    $screen = Native::test(Account::class);
    $screen->set('currentPasswordInput', 'wrong')
        ->set('newPasswordInput', 'new-pass-123')
        ->set('newPasswordConfirmationInput', 'new-pass-123')
        ->call('changePassword');

    expect($screen->get('lastApiError'))->not->toBeNull();
    expect($screen->get('currentPasswordInput'))->toBe('wrong');
});

it('shows the bundled app version', function () {
    Http::fake();

    $screen = Native::test(Account::class);
    $tree = $screen->tree();

    expect(findNodeByRef($tree, 'account-app-version'))->not->toBeNull();
});
