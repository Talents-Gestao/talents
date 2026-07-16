<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_employees', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('email');
            $table->string('address', 500)->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_relationship', 120)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 32)->nullable()->after('emergency_contact_relationship');
            $table->date('admission_date')->nullable()->after('leader_user_id');
            $table->string('work_schedule', 255)->nullable()->after('admission_date');
            $table->string('cpf', 14)->nullable()->after('work_schedule');
            $table->string('rg', 32)->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('company_employees', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'address',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'admission_date',
                'work_schedule',
                'cpf',
                'rg',
            ]);
        });
    }
};
