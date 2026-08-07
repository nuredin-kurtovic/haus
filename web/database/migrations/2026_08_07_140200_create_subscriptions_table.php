<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained();
            $table->enum('status', [
                'cekanje_uplate',
                'aktivna',
                'istekla',
                'otkazana',
                'ponuda',
            ])->default('cekanje_uplate')->index();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->unsignedInteger('free_interventions')->default(0);
            $table->dateTime('renewal_reminder_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
