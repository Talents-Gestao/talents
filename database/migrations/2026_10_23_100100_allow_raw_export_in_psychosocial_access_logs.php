<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registra o acesso interno ao dado bruto (exportação linha a linha da Talents).
 * A regra só existe no Postgres; no SQLite ela fica em PsychosocialAccessLog::RESOURCES.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE psychosocial_access_logs DROP CONSTRAINT IF EXISTS psychosocial_access_logs_resource_check');
        DB::statement("ALTER TABLE psychosocial_access_logs ADD CONSTRAINT psychosocial_access_logs_resource_check CHECK (resource IN ('dashboard', 'document_download', 'export_csv', 'export_json', 'raw_export'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // O log é só de inserção: não apagamos registros para voltar a regra antiga.
        if (DB::table('psychosocial_access_logs')->where('resource', 'raw_export')->exists()) {
            throw new RuntimeException('Rollback bloqueado: existem registros de raw_export no log de acesso.');
        }

        DB::statement('ALTER TABLE psychosocial_access_logs DROP CONSTRAINT IF EXISTS psychosocial_access_logs_resource_check');
        DB::statement("ALTER TABLE psychosocial_access_logs ADD CONSTRAINT psychosocial_access_logs_resource_check CHECK (resource IN ('dashboard', 'document_download', 'export_csv', 'export_json'))");
    }
};
