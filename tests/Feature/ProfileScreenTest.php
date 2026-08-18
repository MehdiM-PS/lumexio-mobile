<?php

use App\NativeComponents\Screens\Profile;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeProfileEndpoints(array $user = ['name' => 'Marie Chevalier', 'email' => 'marie@boutique-principale.fr']): void
{
    Http::fake([
        '*/api/v1/auth/me' => Http::response(['user' => $user]),
        '*/api/v1/auth/profile' => Http::response(['user' => array_merge($user, ['name' => 'Marie Dupont'])]),
    ]);
}

it('loads the current user into the form on mount', function () {
    fakeProfileEndpoints();

    $screen = Native::test(Profile::class);

    expect($screen->get('nameInput'))->toBe('Marie Chevalier')
        ->and($screen->get('emailInput'))->toBe('marie@boutique-principale.fr');
});

it('saves the profile and reflects the server response', function () {
    fakeProfileEndpoints();

    $screen = Native::test(Profile::class);
    $screen->set('nameInput', 'Marie Dupont')->call('save');

    expect($screen->get('nameInput'))->toBe('Marie Dupont');
    expect($screen->get('lastApiError'))->toBeNull();
});

it('surfaces a validation error from the server without clearing the form', function () {
    // Deliberately doesn't call fakeProfileEndpoints() first — Http::fake()'s
    // stub matching is first-registered-wins (PendingRequest::buildStubHandler()
    // takes ->filter()->first() over all accumulated stubCallbacks), so an
    // earlier '*/api/v1/auth/profile' success stub would shadow this test's
    // 422 stub for the same URL rather than being overridden by it.
    Http::fake([
        '*/api/v1/auth/me' => Http::response(['user' => ['name' => 'Marie Chevalier', 'email' => 'marie@boutique-principale.fr']]),
        '*/api/v1/auth/profile' => Http::response(['message' => 'validation error', 'errors' => ['email' => ['Cette adresse est déjà utilisée.']]], 422),
    ]);

    $screen = Native::test(Profile::class);
    $screen->set('emailInput', 'taken@example.com')->call('save');

    expect($screen->get('lastApiError'))->not->toBeNull();
    expect($screen->get('emailInput'))->toBe('taken@example.com');
});

it('changes the password and clears the form on success', function () {
    fakeProfileEndpoints();
    Http::fake(['*/api/v1/auth/password' => Http::response(['message' => 'Mot de passe mis à jour'])]);

    $screen = Native::test(Profile::class);
    $screen->set('currentPasswordInput', 'old-pass')
        ->set('newPasswordInput', 'new-pass-123')
        ->set('newPasswordConfirmationInput', 'new-pass-123')
        ->call('changePassword');

    expect($screen->get('lastApiError'))->toBeNull();
    expect($screen->get('currentPasswordInput'))->toBe('');
    expect($screen->get('newPasswordInput'))->toBe('');
    expect($screen->get('newPasswordConfirmationInput'))->toBe('');
});

it('surfaces a password-change server error and keeps the form filled', function () {
    Http::fake([
        '*/api/v1/auth/me' => Http::response(['user' => ['name' => 'Marie Chevalier', 'email' => 'marie@boutique-principale.fr']]),
        '*/api/v1/auth/password' => Http::response([
            'message' => 'validation error',
            'errors' => ['current_password' => ['Le mot de passe actuel est incorrect.']],
        ], 422),
    ]);

    $screen = Native::test(Profile::class);
    $screen->set('currentPasswordInput', 'wrong')
        ->set('newPasswordInput', 'new-pass-123')
        ->set('newPasswordConfirmationInput', 'new-pass-123')
        ->call('changePassword');

    expect($screen->get('lastApiError'))->not->toBeNull();
    expect($screen->get('currentPasswordInput'))->toBe('wrong');
});
