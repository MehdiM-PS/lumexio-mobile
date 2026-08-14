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
            $response->successful() => $response->json(),
            $response->status() === 401 => throw new UnauthenticatedApiException('Session expirée.'),
            $response->status() === 403 => throw new ProRequiredApiException(
                $response->json('message') ?? 'Fonctionnalité réservée au plan Pro.'
            ),
            $response->status() === 422 => throw new ValidationApiException(
                $response->json('message') ?? 'Erreur de validation.',
                $response->json('errors') ?? []
            ),
            $response->status() === 429 => throw new RateLimitedApiException(
                $response->json('message') ?? 'Trop de requêtes, réessaie dans un instant.'
            ),
            default => throw new ServerErrorApiException(
                $response->json('message') ?? 'Une erreur est survenue. Réessaie plus tard.'
            ),
        };
    }
}
