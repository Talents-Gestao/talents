<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rhid_person_schedule_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('id_person');
            $table->boolean('use_second_lunch_interval')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'id_person']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rhid_person_schedule_preferences');
    }
};
