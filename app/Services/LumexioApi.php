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
use Illuminate\Support\Facades\Http;

class LumexioApi
{
    public function get(string $endpoint, array $query = []): array
    {
        $shopId = LocalState::current()->shop_id;

        if ($shopId && ! array_key_exists('shop_id', $query)) {
            $query['shop_id'] = $shopId;
        }

        return $this->send('get', $endpoint, $query);
    }

    public function post(string $endpoint, array $body = []): array
    {
        return $this->send('post', $endpoint, $body);
    }

    public function patch(string $endpoint, array $body = []): array
    {
        return $this->send('patch', $endpoint, $body);
    }

    private function send(string $method, string $endpoint, array $params): array
    {
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
