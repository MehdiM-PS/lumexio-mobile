<?php

use App\Models\LocalState;
use Illuminate\Support\Facades\DB;

it('creates a single row on first access', function () {
    $state = LocalState::current();

    expect($state->id)->toBe(1)
        ->and(LocalState::count())->toBe(1)
        ->and(LocalState::current()->id)->toBe(1);
});

it('encrypts the token at rest', function () {
    LocalState::current()->update(['token' => 'plain-text-token']);

    $raw = DB::table('local_state')->where('id', 1)->value('token');

    expect($raw)->not->toBe('plain-text-token')
        ->and(LocalState::current()->fresh()->token)->toBe('plain-text-token');
});

it('stores the selected shop id', function () {
    LocalState::current()->update(['shop_id' => 'shop-uuid-123']);

    expect(LocalState::current()->fresh()->shop_id)->toBe('shop-uuid-123');
});

it('clears both token and shop on clearToken', function () {
    LocalState::current()->update(['token' => 'abc', 'shop_id' => 'shop-uuid-123']);

    LocalState::current()->clearToken();

    $fresh = LocalState::current()->fresh();

    expect($fresh->token)->toBeNull()
        ->and($fresh->shop_id)->toBeNull();
});

it('stores a cached active shop name for the shared header', function () {
    LocalState::current()->update(['active_shop_name' => 'Boutique Principale']);

    expect(LocalState::current()->fresh()->active_shop_name)->toBe('Boutique Principale');
});

it('defaults unread_alert_count to zero and stores a cached badge count', function () {
    expect(LocalState::current()->unread_alert_count)->toBe(0);

    LocalState::current()->update(['unread_alert_count' => 4]);

    expect(LocalState::current()->fresh()->unread_alert_count)->toBe(4);
});

it('clears the cached header fields on logout, not just token and shop', function () {
    LocalState::current()->update([
        'token' => 'abc',
        'shop_id' => 'shop-uuid-123',
        'active_shop_name' => 'Boutique Principale',
        'unread_alert_count' => 4,
    ]);

    LocalState::current()->clearToken();

    $fresh = LocalState::current()->fresh();

    expect($fresh->active_shop_name)->toBeNull()
        ->and($fresh->unread_alert_count)->toBe(0);
});
