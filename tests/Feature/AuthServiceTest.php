<?php

use App\Exceptions\Api\ServerErrorApiException;
use App\Models\LocalState;
use App\Services\AuthService;
use Illuminate\Support\Facades\Http;

it('logs in, stores the token, and returns the user', function () {
    Http::fake(['*/auth/login' => Http::response([
        'user' => ['id' => 1, 'name' => 'Mehdi', 'email' => 'mehdi@lumexio.test', 'is_subscribed' => true],
        'token' => '1|abc123',
    ], 200)]);

    $user = app(AuthService::class)->login('mehdi@lumexio.test', 'secret', 'Lumexio iOS');

    expect($user['email'])->toBe('mehdi@lumexio.test')
        ->and(LocalState::current()->fresh()->token)->toBe('1|abc123');

    Http::assertSent(fn ($request) => $request['device_name'] === 'Lumexio iOS'
        && $request['email'] === 'mehdi@lumexio.test'
        && $request['password'] === 'secret');
});

it('returns the current user via me', function () {
    LocalState::current()->update(['token' => 'existing-token']);
    Http::fake(['*/auth/me' => Http::response([
        'user' => ['id' => 1, 'name' => 'Mehdi', 'email' => 'mehdi@lumexio.test', 'is_subscribed' => false],
    ], 200)]);

    expect(app(AuthService::class)->me()['name'])->toBe('Mehdi');
});

it('clears local state on logout even if the remote call fails', function () {
    LocalState::current()->update(['token' => 'existing-token', 'shop_id' => 'shop-1']);
    Http::fake(['*/auth/logout' => Http::response(['message' => 'boom'], 500)]);

    app(AuthService::class)->logout();

    $fresh = LocalState::current()->fresh();
    expect($fresh->token)->toBeNull()->and($fresh->shop_id)->toBeNull();
});

it('reports whether a token is stored', function () {
    expect(app(AuthService::class)->hasToken())->toBeFalse();

    LocalState::current()->update(['token' => 'x']);

    expect(app(AuthService::class)->hasToken())->toBeTrue();
});

it('throws ServerErrorApiException when the login response is missing the token', function () {
    Http::fake(['*/auth/login' => Http::response([
        'user' => ['id' => 1, 'name' => 'Mehdi', 'email' => 'mehdi@lumexio.test', 'is_subscribed' => true],
    ], 200)]);

    app(AuthService::class)->login('mehdi@lumexio.test', 'secret', 'Lumexio iOS');
})->throws(ServerErrorApiException::class, 'Une erreur est survenue. Réessaie plus tard.');

it('throws ServerErrorApiException when the me response is missing the user', function () {
    LocalState::current()->update(['token' => 'existing-token']);
    Http::fake(['*/auth/me' => Http::response([], 200)]);

    app(AuthService::class)->me();
})->throws(ServerErrorApiException::class, 'Une erreur est survenue. Réessaie plus tard.');
