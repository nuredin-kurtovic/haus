<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** HAUS Karton: hronologija po adresi. */
    public function up(): void
    {
        Schema::create('home_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['intervencija', 'pregled', 'napomena']);
            $table->string('title');
            $table->text('body')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_records');
    }
};
