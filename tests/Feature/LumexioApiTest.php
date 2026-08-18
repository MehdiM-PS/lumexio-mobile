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

it('always sends an Accept: application/json header', function () {
    Http::fake(['*' => Http::response(['user' => ['id' => 1]], 200)]);

    app(LumexioApi::class)->get('/auth/me');

    Http::assertSent(fn ($request) => $request->hasHeader('Accept', 'application/json'));
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

it('throws ServerErrorApiException on a 200 response with a non-JSON body (e.g. a captive portal page)', function () {
    Http::fake(['*' => Http::response('<html>captive portal</html>', 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
})->throws(ServerErrorApiException::class, 'Une erreur est survenue. Réessaie plus tard.');

it('throws ServerErrorApiException on a 204 No Content response', function () {
    Http::fake(['*' => Http::response(null, 204)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
})->throws(ServerErrorApiException::class, 'Une erreur est survenue. Réessaie plus tard.');

it('falls back to the default message when the error response has a non-string message field', function () {
    Http::fake(['*' => Http::response(['message' => ['nested' => 'array']], 500)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
})->throws(ServerErrorApiException::class, 'Une erreur est survenue. Réessaie plus tard.');

it('purges the local token and shop_id when a 401 response is received', function () {
    LocalState::current()->update(['token' => 'stale-token', 'shop_id' => 'shop-1']);
    Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    try {
        app(LumexioApi::class)->get('/dashboard/metrics');
        test()->fail('Expected UnauthenticatedApiException was not thrown.');
    } catch (UnauthenticatedApiException) {
        // expected
    }

    $fresh = LocalState::current()->fresh();
    expect($fresh->token)->toBeNull()->and($fresh->shop_id)->toBeNull();
});

it('sends a PATCH request and returns the decoded json body on success', function () {
    Http::fake(['*' => Http::response(['message' => 'Seuil mis à jour'], 200)]);

    $data = app(LumexioApi::class)->patch('/products/1/threshold', ['threshold' => 5]);

    expect($data)->toBe(['message' => 'Seuil mis à jour']);
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['threshold'] === 5);
});

it('throws ValidationApiException on a 422 response from patch()', function () {
    Http::fake(['*' => Http::response(['message' => 'Erreur de validation.', 'errors' => ['threshold' => ['Le seuil doit être positif.']]], 422)]);

    app(LumexioApi::class)->patch('/products/1/threshold', ['threshold' => -1]);
})->throws(ValidationApiException::class);

it('sends a DELETE request with the given params', function () {
    Http::fake(['*/suppliers/5' => Http::response(['message' => 'deleted'], 200)]);

    $result = app(LumexioApi::class)->delete('/suppliers/5', ['confirm' => true]);

    expect($result)->toBe(['message' => 'deleted']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains((string) $request->url(), '/suppliers/5')
        && ($request['confirm'] ?? null) === true);
});

it('serves a repeated GET request from cache instead of hitting the network again', function () {
    Http::fake(['*' => Http::response(['metrics' => []], 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
    app(LumexioApi::class)->get('/dashboard/metrics');

    Http::assertSentCount(1);
});

it('treats GET requests with different query parameters as separate cache entries', function () {
    Http::fake(['*' => Http::response(['metrics' => []], 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics', ['day' => 'today']);
    app(LumexioApi::class)->get('/dashboard/metrics', ['day' => 'yesterday']);

    Http::assertSentCount(2);
});

it('refetches a GET request once the cache TTL has elapsed', function () {
    Http::fake(['*' => Http::response(['metrics' => []], 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
    $this->travel(config('lumexio.cache_ttl') + 1)->seconds();
    app(LumexioApi::class)->get('/dashboard/metrics');

    Http::assertSentCount(2);
});

it('busts the GET cache after a successful POST', function () {
    Http::fake([
        '*/alerts/1/read' => Http::response(['message' => 'ok'], 200),
        '*' => Http::response(['metrics' => []], 200),
    ]);

    app(LumexioApi::class)->get('/dashboard/metrics');
    app(LumexioApi::class)->post('/alerts/1/read');
    app(LumexioApi::class)->get('/dashboard/metrics');

    Http::assertSentCount(3);
});

it('busts the GET cache via LumexioApi::bustCache()', function () {
    Http::fake(['*' => Http::response(['metrics' => []], 200)]);

    app(LumexioApi::class)->get('/dashboard/metrics');
    app(LumexioApi::class)->bustCache();
    app(LumexioApi::class)->get('/dashboard/metrics');

    Http::assertSentCount(2);
});
