<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PurposeMapCampaign;
use App\Services\PurposeMapThemeAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeneratePurposeMapThemesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $campaignId) {}

    public function handle(PurposeMapThemeAnalyzer $analyzer): void
    {
        $cacheKey = PurposeMapThemeAnalyzer::pendingCacheKey($this->campaignId);

        try {
            $campaign = PurposeMapCampaign::query()->find($this->campaignId);
            if (! $campaign) {
                return;
            }

            $analyzer->analyze($campaign);
        } catch (\Throwable $e) {
            Log::error('GeneratePurposeMapThemesJob failed', [
                'campaign_id' => $this->campaignId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            Cache::forget($cacheKey);
        }
    }
}
