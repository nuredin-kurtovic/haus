<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained();
            $table->string('street');
            $table->enum('use', ['zivim', 'izdaje_se', 'prazan'])->default('zivim');
            $table->string('contact_name')->nullable();
            $table->string('contact_note')->nullable();
            $table->unsignedInteger('remaining_visits')->default(0);
            $table->unsignedInteger('remaining_inspections')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_properties');
    }
};
