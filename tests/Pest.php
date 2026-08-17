<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Edge\CallbackRegistry;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    /*
     * Laravel's Http client lets UNMATCHED requests through to the real
     * network by default (PendingRequest::$preventStrayRequests = false) —
     * Http::fake([...]) only stubs the patterns you list, it does NOT return
     * an empty 200 for everything else. With config('lumexio.api_url')
     * defaulting to the live https://lumexio.tech/api/v1, any endpoint a
     * screen hits but a test forgets to fake becomes a real request to
     * PRODUCTION during `php artisan test`.
     *
     * This bit us for real: when HasHeaderChrome's loadCurrentUser() was
     * added to Stock/Alerts, several already-existing tests in those files
     * started silently calling GET /auth/me against production and still
     * passed (a 401 there is swallowed by HandlesApiErrors::callApi()).
     *
     * Preventing stray requests makes that fail loudly instead. The flag is
     * read in Factory::createPendingRequest(), so setting it here survives
     * every later Http::fake() call in the test body.
     */
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The callback id a fresh, unscoped CallbackRegistry would derive for
 * $expression — content-addressed (pure hash of the expression string), so
 * it matches whatever id the screen's own (also unscoped) registry produced
 * when it registered the same event-binding expression (e.g.
 * `@refresh="loadHistory"`). Lets a test assert a wire-tree element is bound
 * to the RIGHT method, not just that some callback is bound — an `isset()`
 * check alone would still pass for a typo like `@refresh="loadHistry"`.
 */
function callbackIdFor(string $expression): int
{
    return (new CallbackRegistry)->register($expression);
}

/**
 * Recursively find a wire-tree node by its `ref`, or null if not found.
 * Shared across screen tests (e.g. Dashboard/ItemDetail chart bars) that
 * need to compare a specific node's resolved style/props rather than just
 * assert an element of a type exists somewhere in the tree.
 */
function findNodeByRef(array $node, string $ref): ?array
{
    if (($node['ref'] ?? null) === $ref) {
        return $node;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (($found = findNodeByRef($child, $ref)) !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * Shared AI-recommendation fixture used by RecommendationsOpenTabTest and
 * RecommendationsActionedTabTest. Lives here (not file-local to either test)
 * because Pest's `--filter` still loads every Feature test file during suite
 * collection, but running a single test file directly (no `--filter`) does
 * NOT load its siblings — a file-local declaration only shared "by accident"
 * of load order breaks that direct-run path with an undefined-function
 * error. Same shared-helper convention as callbackIdFor()/findNodeByRef()
 * above.
 */
function sampleRecommendation(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'type' => 'stock_revenue_risk',
        'type_label' => 'Stock Revenue Risk',
        'group' => 'alerts',
        'group_label' => 'Alertes',
        'priority' => 'high',
        'priority_label' => 'Haute',
        'title' => 'Risque de rupture sur produit populaire',
        'description' => 'Ce produit va manquer de stock avant la prochaine livraison.',
        'data_fields' => [['label' => 'Produit', 'value' => 'T-shirt bleu']],
        'actions' => ['Réapprovisionner avant vendredi'],
        'freshness_label' => 'Nouveau',
        'ref' => 'SKU-1234',
        'is_actioned' => false,
        'actioned_status' => null,
        'rejected_at' => null,
        'created_at' => now()->toIso8601String(),
    ], $overrides);
}
