<?php

use App\NativeComponents\RecommendationsActionedTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

// sampleRecommendation() is declared as an unprefixed global Pest function in
// RecommendationsOpenTabTest.php (Task 2) and is reused here as-is — Pest
// loads every test file in the suite regardless of --filter, so redeclaring
// it here would fatal with "cannot redeclare" and break the whole suite, not
// just this file.
function fakeRecommendationsActionedEndpoints(array $recommendations = [], ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/recommendations/actioned*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/recommendations/*/reopen' => Http::response(['message' => 'Recommandation rouverte'], 200),
        '*/recommendations/actioned*' => Http::response(['recommendations' => $recommendations], 200),
    ]);
}

it('shows actioned recommendations with a resolved status badge', function () {
    fakeRecommendationsActionedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'is_actioned' => true, 'actioned_status' => 'resolved']),
    ]);

    Native::test(RecommendationsActionedTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-status-resolved');
});

it('shows a monitoring status badge for still-detected recommendations', function () {
    fakeRecommendationsActionedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'is_actioned' => true, 'actioned_status' => 'monitoring']),
    ]);

    Native::test(RecommendationsActionedTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-status-monitoring');
});

it('shows no status badge when actioned_status is null', function () {
    fakeRecommendationsActionedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'is_actioned' => true, 'actioned_status' => null]),
    ]);

    Native::test(RecommendationsActionedTab::class)
        ->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-status-resolved')
        ->assertMissingElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-status-monitoring');
});

it('reopens a recommendation via the real button binding and removes it locally', function () {
    fakeRecommendationsActionedEndpoints(recommendations: [
        sampleRecommendation(['id' => 5, 'is_actioned' => true, 'actioned_status' => 'resolved']),
    ]);

    $screen = Native::test(RecommendationsActionedTab::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-5-reopen' && ($n['props']['on_press'] ?? null) === callbackIdFor('reopen(5)'));

    $screen->call('reopen', 5);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations/5/reopen'));
    expect($screen->get('recommendations'))->toHaveCount(0);
});

it('shows an empty state when there are no actioned recommendations', function () {
    fakeRecommendationsActionedEndpoints(recommendations: []);

    Native::test(RecommendationsActionedTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-actioned-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeRecommendationsActionedEndpoints(error: 'boom');

    Native::test(RecommendationsActionedTab::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-actioned-error');
});

it('is fully accessible', function () {
    fakeRecommendationsActionedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'is_actioned' => true, 'actioned_status' => 'resolved']),
    ]);

    Native::test(RecommendationsActionedTab::class)->assertAccessible();
});

// Bug fix precedent (StockAlertsTab/RecommendationsOpenTab): <refreshable>
// must be the OUTERMOST element wrapping the whole screen, or the list never
// claims the remaining vertical space and doesn't scroll.
it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeRecommendationsActionedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'is_actioned' => true, 'actioned_status' => 'resolved']),
    ]);

    $screen = Native::test(RecommendationsActionedTab::class);

    expect($screen->tree()['type'])->toBe('refreshable');
    $screen->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-reopen');
});
