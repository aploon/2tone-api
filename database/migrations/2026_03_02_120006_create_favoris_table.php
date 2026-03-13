<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Favoris : relation N-N utilisateur <-> annonces.
     */
    public function up(): void
    {
        Schema::create('favoris', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('annonce_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['utilisateur_id', 'annonce_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoris');
    }
};
