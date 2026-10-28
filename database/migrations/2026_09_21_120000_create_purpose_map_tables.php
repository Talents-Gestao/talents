<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purpose_map_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->uuid('public_token')->unique();
            $table->string('status', 32)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('themes_analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('purpose_map_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purpose_map_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('sector', 120);
            $table->text('why_work');
            $table->text('dream');
            $table->text('pain_self');
            $table->text('pain_sector');
            $table->text('pain_company');
            $table->string('respondent_cookie', 64)->nullable();
            $table->timestamps();

            $table->index(['purpose_map_campaign_id', 'sector']);
            $table->unique(['purpose_map_campaign_id', 'respondent_cookie'], 'purpose_map_resp_campaign_cookie_unique');
        });

        Schema::create('purpose_map_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purpose_map_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('question_key', 64);
            $table->string('sector', 120)->nullable();
            $table->string('theme', 255);
            $table->unsignedInteger('count')->default(0);
            $table->json('sample_excerpts')->nullable();
            $table->timestamps();

            $table->index(['purpose_map_campaign_id', 'question_key', 'sector'], 'purpose_map_themes_lookup');
        });

        Schema::create('purpose_map_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purpose_map_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 64)->default('general');
            $table->text('message');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['purpose_map_campaign_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purpose_map_insights');
        Schema::dropIfExists('purpose_map_themes');
        Schema::dropIfExists('purpose_map_responses');
        Schema::dropIfExists('purpose_map_campaigns');
    }
};
