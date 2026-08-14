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
