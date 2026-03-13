<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quartiers (ex. Bamako) pour le référencement des annonces.
     */
    public function up(): void
    {
        Schema::create('quartiers', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('ville')->default('Bamako');
            $table->timestamps();

            $table->index(['ville', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quartiers');
    }
};
