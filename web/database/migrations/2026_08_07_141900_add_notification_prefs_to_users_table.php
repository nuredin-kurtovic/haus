<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HAUS nema telefonske pozive, pa korisnik nema telefon.
     * Dodaju se samo preference obavjestenja.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notif_push')->default(true);
            $table->boolean('notif_email')->default(true);
            $table->boolean('notif_marketing')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notif_push', 'notif_email', 'notif_marketing']);
        });
    }
};
