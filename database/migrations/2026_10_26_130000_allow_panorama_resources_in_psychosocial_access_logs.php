<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registra a impressão do panorama e a exportação agregada das perguntas no log de acesso.
 * A regra só existe no Postgres; no SQLite ela fica em PsychosocialAccessLog::RESOURCES.
 */
return new class extends Migration
{
    private const WITH_PANORAMA = "'dashboard', 'document_download', 'export_csv', 'export_json', 'raw_export', 'panorama_print', 'export_items_csv'";

    private const BEFORE = "'dashboard', 'document_download', 'export_csv', 'export_json', 'raw_export'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE psychosocial_access_logs DROP CONSTRAINT IF EXISTS psychosocial_access_logs_resource_check');
        DB::statement('ALTER TABLE psychosocial_access_logs ADD CONSTRAINT psychosocial_access_logs_resource_check CHECK (resource IN ('.self::WITH_PANORAMA.'))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // O log é só de inserção: não apagamos registros para voltar a regra antiga.
        if (DB::table('psychosocial_access_logs')->whereIn('resource', ['panorama_print', 'export_items_csv'])->exists()) {
            throw new RuntimeException('Rollback bloqueado: existem registros de panorama_print/export_items_csv no log de acesso.');
        }

        DB::statement('ALTER TABLE psychosocial_access_logs DROP CONSTRAINT IF EXISTS psychosocial_access_logs_resource_check');
        DB::statement('ALTER TABLE psychosocial_access_logs ADD CONSTRAINT psychosocial_access_logs_resource_check CHECK (resource IN ('.self::BEFORE.'))');
    }
};
