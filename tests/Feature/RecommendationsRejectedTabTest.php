<?php

use App\NativeComponents\RecommendationsRejectedTab;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

// sampleRecommendation() is a shared global Pest function declared in
// tests/Pest.php (alongside callbackIdFor()/findNodeByRef()) and reused here
// as-is — declaring a second definition with the same name would fatal with
// "cannot redeclare" and break the whole suite, not just this file.
function fakeRecommendationsRejectedEndpoints(array $recommendations = [], ?string $error = null): void
{
    if ($error !== null) {
        Http::fake(['*/recommendations/rejected*' => Http::response(['message' => $error], 500)]);

        return;
    }

    Http::fake([
        '*/recommendations/*/unreject' => Http::response(['message' => 'Recommandation restaurée'], 200),
        '*/recommendations/rejected*' => Http::response(['recommendations' => $recommendations], 200),
    ]);
}

it('shows rejected recommendations', function () {
    fakeRecommendationsRejectedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'rejected_at' => now()->toIso8601String()]),
    ]);

    Native::test(RecommendationsRejectedTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-title' && ($n['props']['text'] ?? null) === sampleRecommendation()['title']);
});

it('restores a recommendation via the real button binding and removes it locally', function () {
    fakeRecommendationsRejectedEndpoints(recommendations: [
        sampleRecommendation(['id' => 8, 'rejected_at' => now()->toIso8601String()]),
    ]);

    $screen = Native::test(RecommendationsRejectedTab::class)
        ->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-8-unreject' && ($n['props']['on_press'] ?? null) === callbackIdFor('unreject(8)'));

    $screen->call('unreject', 8);

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/recommendations/8/unreject'));
    expect($screen->get('recommendations'))->toHaveCount(0);
});

it('shows an empty state when there are no rejected recommendations', function () {
    fakeRecommendationsRejectedEndpoints(recommendations: []);

    Native::test(RecommendationsRejectedTab::class)
        ->assertElement('text', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-rejected-empty');
});

it('shows a generic error instead of crashing on failure', function () {
    fakeRecommendationsRejectedEndpoints(error: 'boom');

    Native::test(RecommendationsRejectedTab::class)
        ->assertElement('row', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-rejected-error');
});

it('is fully accessible', function () {
    fakeRecommendationsRejectedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'rejected_at' => now()->toIso8601String()]),
    ]);

    Native::test(RecommendationsRejectedTab::class)->assertAccessible();
});

// Bug fix precedent (StockAlertsTab/RecommendationsOpenTab/RecommendationsActionedTab):
// <refreshable> must be the OUTERMOST element wrapping the whole screen, or the
// list never claims the remaining vertical space and doesn't scroll.
it('wraps the entire screen in a refreshable element, not just the list', function () {
    fakeRecommendationsRejectedEndpoints(recommendations: [
        sampleRecommendation(['id' => 1, 'rejected_at' => now()->toIso8601String()]),
    ]);

    $screen = Native::test(RecommendationsRejectedTab::class);

    expect($screen->tree()['type'])->toBe('refreshable');
    $screen->assertElement('button', fn (array $n): bool => ($n['ref'] ?? null) === 'reco-1-unreject');
});
