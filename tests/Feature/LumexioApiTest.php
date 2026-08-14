<?php

use App\Exceptions\Api\NetworkUnavailableApiException;
use App\Exceptions\Api\ProRequiredApiException;
use App\Exceptions\Api\RateLimitedApiException;
use App\Exceptions\Api\ServerErrorApiException;
use App\Exceptions\Api\UnauthenticatedApiException;
use App\Exceptions\Api\ValidationApiException;
use App\Models\LocalState;
use App\Services\LumexioApi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('sends a bearer token when one is stored locally', function () {
    LocalState::current()->update(['token' => 'secret-token']);
    Http::fake(['*' => Http::response(['user' => ['id' => 1]], 200)]);

    app(LumexioApi::class)->get('/auth/me');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('sends no Authorization header when no token is stored', function () {
    Http::fake(['*' => Http::response(['user' => null], 200)]);

    app(LumexioApi::class)->get('/auth/me');

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
});

it('injects the stored shop_id into GET requests that do not already set one', function () {
    LocalState::current()->update(['shop_id' => 'shop-uuid']);
    Http::fake(['*' => Http::response(['metrics' => []], 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics');

    Http::assertSent(fn ($request) => $request['shop_id'] === 'shop-uuid');
});

it('does not override an explicit shop_id on GET requests', function () {
    LocalState::current()->update(['shop_id' => 'shop-uuid']);
    Http::fake(['*' => Http::response(['metrics' => []], 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics', ['shop_id' => 'explicit-uuid']);

    Http::assertSent(fn ($request) => $request['shop_id'] === 'explicit-uuid');
});

it('throws UnauthenticatedApiException on a 401 response', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
})->throws(UnauthenticatedApiException::class);

it('throws ProRequiredApiException on a 403 response with the API message', function () {
    Http::fake(['*' => Http::response(['message' => 'Fonctionnalité Pro requise.'], 403)]);

    app(LumexioApi::class)->get('/forecasts');
})->throws(ProRequiredApiException::class, 'Fonctionnalité Pro requise.');

it('throws ValidationApiException on a 422 response and exposes field errors', function () {
    Http::fake(['*' => Http::response([
        'message' => 'The given data was invalid.',
        'errors' => ['email' => ['These credentials do not match our records.']],
    ], 422)]);

    try {
        app(LumexioApi::class)->post('/auth/login', ['email' => 'x', 'password' => 'y', 'device_name' => 'z']);
        $this->fail('Expected ValidationApiException was not thrown.');
    } catch (ValidationApiException $e) {
        expect($e->errors)->toBe(['email' => ['These credentials do not match our records.']]);
    }
});

it('throws RateLimitedApiException on a 429 response', function () {
    Http::fake(['*' => Http::response(['message' => 'Too many requests.'], 429)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
})->throws(RateLimitedApiException::class);

it('throws NetworkUnavailableApiException when the request cannot connect at all', function () {
    Http::fake(fn () => throw new ConnectionException('Could not connect'));

    app(LumexioApi::class)->get('/dashboard/metrics');
})->throws(NetworkUnavailableApiException::class);

it('throws ServerErrorApiException on a 500 response with the API message', function () {
    Http::fake(['*' => Http::response(['message' => 'Server Error'], 500)]);

    app(LumexioApi::class)->get('/dashboard/charts');
})->throws(ServerErrorApiException::class, 'Server Error');

it('throws ServerErrorApiException with a generic message when the 500 response has none', function () {
    Http::fake(['*' => Http::response([], 500)]);

    app(LumexioApi::class)->get('/dashboard/charts');
})->throws(ServerErrorApiException::class, 'Une erreur est survenue. Réessaie plus tard.');

it('returns the decoded json body on success', function () {
    Http::fake(['*' => Http::response(['metrics' => ['revenue_today' => 12.5]], 200)]);

    expect(app(LumexioApi::class)->get('/dashboard/metrics'))
        ->toBe(['metrics' => ['revenue_today' => 12.5]]);
});
