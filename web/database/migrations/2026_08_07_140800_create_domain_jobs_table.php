<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domenska tabela naloga. Laravelova queue tabela je preimenovana u queue_jobs
     * (config/queue.php), pa ime `jobs` ostaje domenu.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_category_id')->constrained();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['redovno', 'garancija', 'pregled'])->default('redovno')->index();
            $table->enum('status', ['novo', 'zakazano', 'u_toku', 'zavrseno'])->default('novo')->index();
            $table->foreignId('parent_job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->text('description');
            $table->boolean('is_emergency')->default(false);
            $table->string('preferred_window')->nullable();
            $table->dateTime('scheduled_window_start')->nullable();
            $table->dateTime('scheduled_window_end')->nullable();
            $table->dateTime('deadline_at')->index();
            $table->dateTime('deadline_missed_at')->nullable();
            $table->text('findings')->nullable();
            $table->date('warranty_until')->nullable();
            $table->enum('visit_source', ['kredit', 'izlazak', 'naplata'])->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
