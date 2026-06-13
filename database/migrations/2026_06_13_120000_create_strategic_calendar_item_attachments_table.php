<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_calendar_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategic_calendar_item_id')
                ->constrained('strategic_calendar_items')
                ->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('strategic_calendar_item_id');
        });

        if (Schema::hasColumn('strategic_calendar_items', 'attachment_path')) {
            $rows = DB::table('strategic_calendar_items')
                ->whereNotNull('attachment_path')
                ->where('attachment_path', '!=', '')
                ->get(['id', 'attachment_disk', 'attachment_path', 'attachment_original_name', 'attachment_mime', 'attachment_size', 'created_at', 'updated_at']);

            foreach ($rows as $row) {
                DB::table('strategic_calendar_item_attachments')->insert([
                    'strategic_calendar_item_id' => $row->id,
                    'disk' => $row->attachment_disk ?? 'public',
                    'path' => $row->attachment_path,
                    'original_name' => $row->attachment_original_name ?? 'anexo',
                    'mime' => $row->attachment_mime,
                    'size' => $row->attachment_size,
                    'uploaded_by_user_id' => null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            Schema::table('strategic_calendar_items', function (Blueprint $table) {
                $table->dropColumn([
                    'attachment_disk',
                    'attachment_path',
                    'attachment_original_name',
                    'attachment_mime',
                    'attachment_size',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('strategic_calendar_items', function (Blueprint $table) {
            if (! Schema::hasColumn('strategic_calendar_items', 'attachment_disk')) {
                $table->string('attachment_disk')->nullable()->after('recurrence_ends_on');
                $table->string('attachment_path')->nullable()->after('attachment_disk');
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
                $table->string('attachment_mime')->nullable()->after('attachment_original_name');
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            }
        });

        $attachments = DB::table('strategic_calendar_item_attachments')
            ->orderBy('strategic_calendar_item_id')
            ->orderBy('id')
            ->get();

        $firstByItem = [];
        foreach ($attachments as $attachment) {
            if (! isset($firstByItem[$attachment->strategic_calendar_item_id])) {
                $firstByItem[$attachment->strategic_calendar_item_id] = $attachment;
            }
        }

        foreach ($firstByItem as $itemId => $attachment) {
            DB::table('strategic_calendar_items')
                ->where('id', $itemId)
                ->update([
                    'attachment_disk' => $attachment->disk,
                    'attachment_path' => $attachment->path,
                    'attachment_original_name' => $attachment->original_name,
                    'attachment_mime' => $attachment->mime,
                    'attachment_size' => $attachment->size,
                ]);
        }

        Schema::dropIfExists('strategic_calendar_item_attachments');
    }
};
