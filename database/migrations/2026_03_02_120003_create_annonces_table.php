<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Annonces immobilières. Visible uniquement si payée (contrainte métier).
     */
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quartier_id')->constrained()->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('type_bien', [
                'Villa',
                'Maison',
                'Appartement',
                'Duplex/Triplex',
                'Immeuble',
                'Studio',
                'Bureau',
                'Terrain',
            ]);
            $table->unsignedInteger('prix'); // en FCFA (XOF)
            $table->string('statut_publication', 30)->default('brouillon'); // brouillon | en_attente | payee | publiee | rejetee
            $table->unsignedTinyInteger('chambres')->default(0);
            $table->unsignedTinyInteger('salles_de_bain')->default(0);
            $table->unsignedInteger('surface_m2')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index(['statut_publication', 'created_at']);
            $table->index('quartier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};
