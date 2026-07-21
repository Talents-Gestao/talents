<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Exceptions\RhidApiException;
use App\Exceptions\RhidDomainChoiceRequiredException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait RespondsWithRhidJson
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     */
    protected function rhidJsonOrError(callable $callback): JsonResponse|Response
    {
        $jsonFlags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $jsonFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        try {
            return response()->json($callback(), 200, [], $jsonFlags);
        } catch (RhidDomainChoiceRequiredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'needs_domain' => true,
                'domains' => $e->listCustomer,
            ], 422, [], $jsonFlags);
        } catch (RhidApiException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'payload' => $e->payload,
            ], 422, [], $jsonFlags);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Falha na integração com o RHID. Verifique os logs do servidor ou tente novamente.',
                'debug_message' => config('app.debug') ? $e->getMessage() : null,
                'debug_type' => config('app.debug') ? $e::class : null,
            ], 500, [], $jsonFlags);
        }
    }
}
