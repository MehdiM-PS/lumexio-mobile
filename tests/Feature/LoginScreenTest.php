<?php

use App\Models\LocalState;
use App\NativeComponents\Screens\Login;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('renders the login form', function () {
    Native::test(Login::class)
        ->assertSee('Lumexio')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'email-input')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'password-input')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'submit-button');
});

it('logs in and navigates to shop selection on success', function () {
    Http::fake(['*/auth/login' => Http::response([
        'user' => ['id' => 1, 'name' => 'Mehdi', 'email' => 'mehdi@lumexio.test', 'is_subscribed' => true],
        'token' => '1|abc123',
    ], 200)]);

    Native::test(Login::class)
        ->set('email', 'mehdi@lumexio.test')
        ->set('password', 'secret')
        ->call('submit')
        ->assertReplacedWith('/shops/select');

    expect(LocalState::current()->fresh()->token)->toBe('1|abc123');
});

it('shows the API error message on invalid credentials', function () {
    Http::fake(['*/auth/login' => Http::response([
        'message' => 'The given data was invalid.',
        'errors' => ['email' => ['Ces identifiants ne correspondent pas.']],
    ], 422)]);

    Native::test(Login::class)
        ->set('email', 'mehdi@lumexio.test')
        ->set('password', 'wrong')
        ->call('submit')
        ->assertNoNavigation()
        ->assertSee('Ces identifiants ne correspondent pas.');
});

it('is fully accessible', function () {
    Native::test(Login::class)->assertAccessible();
});
