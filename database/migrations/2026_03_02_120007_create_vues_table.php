<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vues / consultations d'annonces (stats pour propriétaire).
     */
    public function up(): void
    {
        Schema::create('vues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annonce_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->timestamp('date_vue')->useCurrent();
            $table->timestamps();

            $table->index(['annonce_id', 'date_vue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vues');
    }
};
