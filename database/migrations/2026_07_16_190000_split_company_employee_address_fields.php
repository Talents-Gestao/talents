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
        Schema::table('company_employees', function (Blueprint $table) {
            $table->string('address_zip', 16)->nullable()->after('phone');
            $table->string('address_street', 255)->nullable()->after('address_zip');
            $table->string('address_number', 32)->nullable()->after('address_street');
            $table->string('address_complement', 120)->nullable()->after('address_number');
            $table->string('address_neighborhood', 120)->nullable()->after('address_complement');
            $table->string('address_city', 120)->nullable()->after('address_neighborhood');
            $table->string('address_state', 2)->nullable()->after('address_city');
        });

        if (Schema::hasColumn('company_employees', 'address')) {
            DB::table('company_employees')
                ->whereNotNull('address')
                ->where('address', '!=', '')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('company_employees')
                            ->where('id', $row->id)
                            ->update(['address_street' => $row->address]);
                    }
                });

            Schema::table('company_employees', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }

    public function down(): void
    {
        Schema::table('company_employees', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('phone');
        });

        DB::table('company_employees')
            ->whereNotNull('address_street')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $parts = array_filter([
                        $row->address_street,
                        $row->address_number ? 'nº '.$row->address_number : null,
                        $row->address_complement,
                        $row->address_neighborhood,
                        $row->address_city && $row->address_state
                            ? $row->address_city.' — '.$row->address_state
                            : ($row->address_city ?: $row->address_state),
                        $row->address_zip ? 'CEP '.$row->address_zip : null,
                    ]);

                    DB::table('company_employees')
                        ->where('id', $row->id)
                        ->update(['address' => implode(', ', $parts)]);
                }
            });

        Schema::table('company_employees', function (Blueprint $table) {
            $table->dropColumn([
                'address_zip',
                'address_street',
                'address_number',
                'address_complement',
                'address_neighborhood',
                'address_city',
                'address_state',
            ]);
        });
    }
};
