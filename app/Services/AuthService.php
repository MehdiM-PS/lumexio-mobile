<?php

namespace App\Services;

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

        LocalState::current()->update(['token' => $data['token']]);

        return $data['user'];
    }

    public function me(): array
    {
        return $this->api->get('/auth/me')['user'];
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
