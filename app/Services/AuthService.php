<?php

namespace App\Services;

use App\Exceptions\Api\ServerErrorApiException;
use App\Models\LocalState;

class AuthService
{
    public function __construct(private LumexioApi $api) {}

    public function login(string $email, string $password, string $deviceName): array
    {
        $data = $this->api->post('/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName,
        ]);

        $token = $data['token'] ?? throw new ServerErrorApiException('Une erreur est survenue. Réessaie plus tard.');
        $user = $data['user'] ?? throw new ServerErrorApiException('Une erreur est survenue. Réessaie plus tard.');

        LocalState::current()->update(['token' => $token]);

        return $user;
    }

    public function me(): array
    {
        $data = $this->api->get('/auth/me');

        return $data['user'] ?? throw new ServerErrorApiException('Une erreur est survenue. Réessaie plus tard.');
    }

    public function logout(): void
    {
        try {
            $this->api->post('/auth/logout');
        } catch (\Throwable) {
            // Best-effort remote revoke — the local session is cleared regardless.
        } finally {
            LocalState::current()->clearToken();
        }
    }

    public function hasToken(): bool
    {
        return filled(LocalState::current()->token);
    }
}
