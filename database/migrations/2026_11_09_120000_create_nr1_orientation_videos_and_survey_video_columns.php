<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr1_orientation_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('role', 16); // intro | closing
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->boolean('intro_video_required')->default(false)->after('uses_invitations');
            $table->boolean('closing_video_required')->default(false)->after('intro_video_required');
            $table->foreignId('intro_video_id')->nullable()->after('closing_video_required')
                ->constrained('nr1_orientation_videos')->nullOnDelete();
            $table->foreignId('closing_video_id')->nullable()->after('intro_video_id')
                ->constrained('nr1_orientation_videos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('intro_video_id');
            $table->dropConstrainedForeignId('closing_video_id');
            $table->dropColumn(['intro_video_required', 'closing_video_required']);
        });

        Schema::dropIfExists('nr1_orientation_videos');
    }
};
