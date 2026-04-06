<?php

namespace App\Services\Rhid;

use App\Models\Company;
use App\Models\RhidAuditLog;
use App\Models\User;

class RhidAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $requestSummary
     * @param  array<string, mixed>|null  $responseSummary
     */
    public function log(
        Company $company,
        ?User $user,
        string $action,
        ?string $endpoint,
        ?array $requestSummary,
        ?array $responseSummary,
        ?int $httpStatus,
    ): void {
        RhidAuditLog::query()->create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'action' => $action,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'request_summary' => $requestSummary,
            'response_summary' => $responseSummary,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function maskSensitive(array $data): array
    {
        $masked = $data;
        $keys = ['password', 'newPassword', 'passwordConfirmation', 'rhid_password', 'user', 'rhid_email'];

        array_walk_recursive($masked, function (&$value, $key) use ($keys) {
            if (is_string($key) && in_array($key, $keys, true) && is_string($value) && $value !== '') {
                $value = '***';
            }
        });

        return $masked;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function summarizeJson(?array $json, int $maxDepth = 4): ?array
    {
        if ($json === null) {
            return null;
        }

        $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return ['_error' => 'json_encode_failed'];
        }

        if (strlen($encoded) > 8000) {
            return [
                '_truncated' => true,
                'preview' => substr($encoded, 0, 4000).'…',
            ];
        }

        return self::maskSensitive($json);
    }
}
