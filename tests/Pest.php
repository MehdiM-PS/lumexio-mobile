<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
