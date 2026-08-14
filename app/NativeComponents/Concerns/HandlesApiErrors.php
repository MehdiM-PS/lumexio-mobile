<?php

namespace App\NativeComponents\Concerns;

use App\Exceptions\Api\NetworkUnavailableApiException;
use App\Exceptions\Api\ProRequiredApiException;
use App\Exceptions\Api\RateLimitedApiException;
use App\Exceptions\Api\UnauthenticatedApiException;
use App\Exceptions\Api\ValidationApiException;
use Closure;
use Native\Mobile\Facades\Dialog;

trait HandlesApiErrors
{
    public ?string $lastApiError = null;

    protected function callApi(Closure $call): mixed
    {
        $this->lastApiError = null;

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
        }

        return null;
    }
}
