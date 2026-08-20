<?php

use App\Models\LocalState;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\System;

it('registers the token with the API and stores it locally', function () {
    Device::shouldReceive('getId')->andReturn('device-abc');
    System::shouldReceive('isIos')->andReturn(true);
    Http::fake(['*/auth/push-token' => Http::response(['message' => 'OK'], 200)]);

    app(PushNotificationService::class)->register('token-123');

    expect(LocalState::current()->fresh()->push_token)->toBe('token-123');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains((string) $request->url(), '/auth/push-token')
        && $request['token'] === 'token-123'
        && $request['platform'] === 'ios'
        && $request['device_id'] === 'device-abc');
});

it('reports android as the platform on android devices', function () {
    Device::shouldReceive('getId')->andReturn('device-abc');
    System::shouldReceive('isIos')->andReturn(false);
    Http::fake(['*/auth/push-token' => Http::response(['message' => 'OK'], 200)]);

    app(PushNotificationService::class)->register('token-123');

    Http::assertSent(fn ($request) => $request['platform'] === 'android');
});

it('skips registration when no device id is available', function () {
    Device::shouldReceive('getId')->andReturn(null);
    Http::fake();

    app(PushNotificationService::class)->register('token-123');

    Http::assertNothingSent();
    expect(LocalState::current()->fresh()->push_token)->toBeNull();
});

it('unregisters the token and clears it locally even if the remote call fails', function () {
    LocalState::current()->update(['push_token' => 'token-123']);
    Device::shouldReceive('getId')->andReturn('device-abc');
    Http::fake(['*/auth/push-token' => Http::response(['message' => 'boom'], 500)]);

    app(PushNotificationService::class)->unregister();

    expect(LocalState::current()->fresh()->push_token)->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains((string) $request->url(), '/auth/push-token')
        && $request['device_id'] === 'device-abc');
});

it('skips the remote unregister call when no device id is available but still clears the local token', function () {
    LocalState::current()->update(['push_token' => 'token-123']);
    Device::shouldReceive('getId')->andReturn(null);
    Http::fake();

    app(PushNotificationService::class)->unregister();

    Http::assertNothingSent();
    expect(LocalState::current()->fresh()->push_token)->toBeNull();
});
