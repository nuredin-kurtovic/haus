<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['pretplata', 'rad']);
            $table->decimal('labor_total', 10, 2)->default(0);
            $table->decimal('material_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', [
                'nenaplaceno',
                'placeno',
                'refundirano',
                'djelimicno_refundirano',
                'bez_naplate',
            ])->default('nenaplaceno')->index();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
