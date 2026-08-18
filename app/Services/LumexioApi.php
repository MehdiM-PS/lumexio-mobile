<?php

namespace App\Services;

use App\Exceptions\Api\NetworkUnavailableApiException;
use App\Exceptions\Api\ProRequiredApiException;
use App\Exceptions\Api\RateLimitedApiException;
use App\Exceptions\Api\ServerErrorApiException;
use App\Exceptions\Api\UnauthenticatedApiException;
use App\Exceptions\Api\ValidationApiException;
use App\Models\LocalState;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LumexioApi
{
    private const string CACHE_PREFIX = 'lumexio_api';

    public function get(string $endpoint, array $query = []): array
    {
        $shopId = LocalState::current()->shop_id;

        if ($shopId && ! array_key_exists('shop_id', $query)) {
            $query['shop_id'] = $shopId;
        }

        return Cache::remember(
            $this->cacheKey($endpoint, $query),
            now()->addSeconds(config('lumexio.cache_ttl')),
            fn () => $this->send('get', $endpoint, $query),
        );
    }

    /**
     * Invalidate every cached GET response. Called automatically before any
     * mutating request (see send()); exposed publicly so callers can force a
     * full refresh (e.g. pull-to-refresh) without waiting out the TTL.
     *
     * Bumps a generation number baked into every cache key rather than
     * Cache::flush(): NativePHP's own NativeCallbacks registry durably stores
     * pending native-callback closures (camera pickers, OAuth flows, ...) in
     * this same default cache store, so a blanket flush on every mutation
     * risks wiping one of those mid-flight. The trade-off is that GET
     * responses from a previous generation become unreachable but aren't
     * physically deleted until their TTL naturally expires — on-device rows
     * accumulate slowly over a long session rather than being freed
     * immediately, which is judged an acceptable cost given the 15s TTL.
     */
    public function bustCache(): void
    {
        if (! Cache::add(self::CACHE_PREFIX.':generation', 1)) {
            Cache::increment(self::CACHE_PREFIX.':generation');
        }
    }

    private function cacheKey(string $endpoint, array $query): string
    {
        $generation = Cache::get(self::CACHE_PREFIX.':generation', 0);

        return self::CACHE_PREFIX.":{$generation}:{$endpoint}:".md5(serialize($query));
    }

    public function post(string $endpoint, array $body = []): array
    {
        return $this->send('post', $endpoint, $body);
    }

    public function patch(string $endpoint, array $body = []): array
    {
        return $this->send('patch', $endpoint, $body);
    }

    public function delete(string $endpoint, array $params = []): array
    {
        return $this->send('delete', $endpoint, $params);
    }

    private function send(string $method, string $endpoint, array $params): array
    {
        if ($method !== 'get') {
            $this->bustCache();
        }

        $token = LocalState::current()->token;

        $request = Http::baseUrl(config('lumexio.api_url'))
            ->timeout(config('lumexio.timeout'));

        if ($token) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->{$method}($endpoint, $params);
        } catch (ConnectionException) {
            throw new NetworkUnavailableApiException('Connexion indisponible. Vérifie ta connexion et réessaie.');
        }

        return match (true) {
            $response->successful() => is_array($body = $response->json())
                ? $body
                : throw new ServerErrorApiException('Une erreur est survenue. Réessaie plus tard.'),
            $response->status() === 401 => throw tap(
                new UnauthenticatedApiException('Session expirée.'),
                fn () => LocalState::current()->clearToken()
            ),
            $response->status() === 403 => throw new ProRequiredApiException(
                $this->messageOrDefault($response, 'Fonctionnalité réservée au plan Pro.')
            ),
            $response->status() === 422 => throw new ValidationApiException(
                $this->messageOrDefault($response, 'Erreur de validation.'),
                $response->json('errors') ?? []
            ),
            $response->status() === 429 => throw new RateLimitedApiException(
                $this->messageOrDefault($response, 'Trop de requêtes, réessaie dans un instant.')
            ),
            default => throw new ServerErrorApiException(
                $this->messageOrDefault($response, 'Une erreur est survenue. Réessaie plus tard.')
            ),
        };
    }

    /**
     * Read the `message` field from an error response body, falling back to
     * $default when it is missing OR present but not a string (the API
     * contract expects a string, but a malformed/unexpected body should
     * degrade gracefully instead of interpolating a non-scalar value into
     * an exception message).
     */
    private function messageOrDefault(Response $response, string $default): string
    {
        $message = $response->json('message');

        return is_string($message) ? $message : $default;
    }
}
