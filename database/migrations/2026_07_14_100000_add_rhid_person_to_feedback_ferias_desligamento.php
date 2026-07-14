<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('rhid_person_id')->nullable()->after('company_employee_id');
            $table->string('employee_name')->nullable()->after('rhid_person_id');
            $table->string('employee_email')->nullable()->after('employee_name');
            $table->index(['company_id', 'rhid_person_id']);
        });

        Schema::table('exit_interviews', function (Blueprint $table) {
            $table->unsignedBigInteger('rhid_person_id')->nullable()->after('company_employee_id');
            $table->string('employee_name')->nullable()->after('rhid_person_id');
            $table->string('employee_email')->nullable()->after('employee_name');
            $table->index(['company_id', 'rhid_person_id']);
        });

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->unsignedBigInteger('rhid_person_id')->nullable()->after('company_employee_id');
            $table->string('employee_name')->nullable()->after('rhid_person_id');
            $table->string('employee_email')->nullable()->after('employee_name');
            $table->index(['company_id', 'rhid_person_id']);
        });

        $this->makeCompanyEmployeeNullable('feedback_sessions');
        $this->makeCompanyEmployeeNullable('exit_interviews');
        $this->makeCompanyEmployeeNullable('employee_leaves');
    }

    public function down(): void
    {
        Schema::table('feedback_sessions', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'rhid_person_id']);
            $table->dropColumn(['rhid_person_id', 'employee_name', 'employee_email']);
        });

        Schema::table('exit_interviews', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'rhid_person_id']);
            $table->dropColumn(['rhid_person_id', 'employee_name', 'employee_email']);
        });

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'rhid_person_id']);
            $table->dropColumn(['rhid_person_id', 'employee_name', 'employee_email']);
        });
    }

    private function makeCompanyEmployeeNullable(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropForeign(['company_employee_id']);
        });

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN company_employee_id DROP NOT NULL");
        } elseif ($driver === 'sqlite') {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('company_employee_id')->nullable()->change();
            });
        } else {
            DB::statement("ALTER TABLE {$table} MODIFY company_employee_id BIGINT UNSIGNED NULL");
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreign('company_employee_id')
                ->references('id')
                ->on('company_employees')
                ->nullOnDelete();
        });
    }
};
