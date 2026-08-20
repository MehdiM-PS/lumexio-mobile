<?php

namespace App\Services;

use App\Models\LocalState;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\System;

class PushNotificationService
{
    public function __construct(private LumexioApi $api) {}

    public function register(string $token): void
    {
        $deviceId = Device::getId();

        if (blank($deviceId)) {
            return;
        }

        $this->api->post('/auth/push-token', [
            'token' => $token,
            'platform' => System::isIos() ? 'ios' : 'android',
            'device_id' => $deviceId,
        ]);

        LocalState::current()->update(['push_token' => $token]);
    }

    public function unregister(): void
    {
        $deviceId = Device::getId();

        try {
            if (filled($deviceId)) {
                $this->api->delete('/auth/push-token', ['device_id' => $deviceId]);
            }
        } catch (\Throwable) {
            // Best-effort remote unregister — the local token is cleared regardless.
        } finally {
            LocalState::current()->update(['push_token' => null]);
        }
    }
}
