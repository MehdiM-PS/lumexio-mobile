<?php

namespace App\NativeComponents\Concerns;

use App\Exceptions\Api\NetworkUnavailableApiException;
use App\Exceptions\Api\ProRequiredApiException;
use App\Exceptions\Api\RateLimitedApiException;
use App\Exceptions\Api\ServerErrorApiException;
use App\Exceptions\Api\UnauthenticatedApiException;
use App\Exceptions\Api\ValidationApiException;
use App\Services\LumexioApi;
use Closure;
use Native\Mobile\Facades\Dialog;

trait HandlesApiErrors
{
    public ?string $lastApiError = null;

    /**
     * Execute an API call and handle common exceptions.
     *
     * Callers are responsible for invoking resetApiError() before the first
     * callApi() call in their action method. This allows actions with multiple
     * sequential API calls to preserve an earlier error if a later call succeeds.
     */
    protected function callApi(Closure $call): mixed
    {
        try {
            return $call();
        } catch (UnauthenticatedApiException) {
            $this->replace('/login');
        } catch (ValidationApiException $e) {
            $this->lastApiError = collect($e->errors)->flatten()->first() ?? $e->getMessage();
        } catch (ProRequiredApiException $e) {
            $this->lastApiError = $e->getMessage();
            Dialog::toast($e->getMessage());
        } catch (RateLimitedApiException $e) {
            $this->lastApiError = $e->getMessage();
            Dialog::toast($e->getMessage());
        } catch (NetworkUnavailableApiException $e) {
            $this->lastApiError = $e->getMessage();
        } catch (ServerErrorApiException $e) {
            $this->lastApiError = $e->getMessage();
        }

        return null;
    }

    protected function resetApiError(): void
    {
        $this->lastApiError = null;
    }

    /**
     * Force the next callApi() calls in this action to hit the network
     * instead of a short-lived cached response. Call at the top of an action
     * bound to an explicit user-initiated refresh (e.g. pull-to-refresh) —
     * mount()-triggered loads should stay cached.
     */
    protected function bustApiCache(): void
    {
        app(LumexioApi::class)->bustCache();
    }
}
