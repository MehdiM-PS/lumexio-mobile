<?php

use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeRecommendationsScreenEndpoints(): void
{
    Http::fake([
        '*/recommendations/actioned*' => Http::response(['recommendations' => []], 200),
        '*/recommendations/rejected*' => Http::response(['recommendations' => []], 200),
        '*/recommendations*' => Http::response([
            'recommendations' => [],
            'stats' => ['total' => 0, 'high_priority' => 0],
        ], 200),
    ]);
}

it('shows the tab bar with a Recommendations tab', function () {
    fakeRecommendationsScreenEndpoints();

    Native::visit('/recommendations')
        ->assertHasTab('Accueil')
        ->assertHasTab('Reco')
        ->assertTabActive('Reco');
});

it('shows the inner tab-row with three tabs', function () {
    fakeRecommendationsScreenEndpoints();

    Native::visit('/recommendations')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Ouvertes')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Traitées')
        ->assertElement('tab', fn (array $n): bool => ($n['props']['label'] ?? null) === 'Rejetées');
});

it('shows the open tab by default and switches to actioned and rejected', function () {
    fakeRecommendationsScreenEndpoints();

    Native::visit('/recommendations')
        ->assertSee('Aucune recommandation pour le moment.')
        ->set('activeTab', 1)
        ->assertSee('Aucune recommandation traitée.')
        ->set('activeTab', 2)
        ->assertSee('Aucune recommandation rejetée.');
});
