<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('commercial_settings')->update([
            'pdf_observacoes' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sem reversão — o texto padrão anterior não deve ser restaurado.
    }
};
