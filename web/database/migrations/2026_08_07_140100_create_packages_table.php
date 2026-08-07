<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_year', 8, 2);
            $table->unsignedInteger('visits_per_year');
            $table->unsignedInteger('deadline_hours');
            $table->unsignedInteger('emergency_deadline_hours');
            $table->boolean('emergency_included')->default(false);
            $table->unsignedInteger('labor_discount_pct')->default(0);
            $table->unsignedInteger('material_discount_pct')->default(0);
            $table->unsignedInteger('inspections_per_year')->default(0);
            $table->unsignedInteger('warranty_months')->default(0);
            $table->boolean('is_per_apartment')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
