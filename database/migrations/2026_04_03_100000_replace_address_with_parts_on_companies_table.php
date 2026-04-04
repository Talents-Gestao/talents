<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'address')) {
                $table->dropColumn('address');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_street', 255)->nullable()->after('segment');
            $table->string('address_neighborhood', 120)->nullable()->after('address_street');
            $table->string('address_city', 120)->nullable()->after('address_neighborhood');
            $table->string('address_state', 2)->nullable()->after('address_city');
            $table->string('address_zip', 12)->nullable()->after('address_state');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'address_street',
                'address_neighborhood',
                'address_city',
                'address_state',
                'address_zip',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->text('address')->nullable()->after('segment');
        });
    }
};
