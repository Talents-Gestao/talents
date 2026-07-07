<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TalentsSeeder::class,
            MethodologyFormTemplateSeeder::class,
            FeedbackTemplateSeeder::class,
            FeedbackDemoSeeder::class,
            CommercialSellersSeeder::class,
            CommercialDemoSeeder::class,
            InterviewQuestionnaireSeeder::class,
        ]);

        try {
            $this->call(ContractTemplateSeeder::class);
        } catch (\Throwable $e) {
            Log::warning('[DatabaseSeeder] ContractTemplateSeeder ignorado.', [
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $this->call(TalentsCompanyProfileSeeder::class);
        } catch (\Throwable $e) {
            Log::warning('[DatabaseSeeder] TalentsCompanyProfileSeeder ignorado.', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
