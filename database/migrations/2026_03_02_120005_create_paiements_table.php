<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paiement lié à une annonce (publication). Annonce visible seulement si payée.
     */
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annonce_id')->constrained()->cascadeOnDelete();
            $table->decimal('montant', 12, 2);
            $table->string('statut', 30)->default('en_attente'); // en_attente | valide | echoue | rembourse
            $table->string('methode', 50)->nullable(); // orange_money | mtn_money | wave | etc.
            $table->string('reference', 100)->nullable()->unique();
            $table->timestamp('date_paiement')->nullable();
            $table->timestamps();

            $table->index(['annonce_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
