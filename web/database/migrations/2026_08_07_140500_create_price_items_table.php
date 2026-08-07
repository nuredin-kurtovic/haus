<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('base_price', 8, 2);
            $table->decimal('draft_base_price', 8, 2)->nullable();
            $table->string('unit')->default('pozicija');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
