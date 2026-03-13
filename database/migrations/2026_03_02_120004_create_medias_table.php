<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Médias par annonce : images + vidéo 3D (obligatoire).
     */
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annonce_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // image | video_3d
            $table->string('url');
            $table->unsignedInteger('poids')->nullable(); // taille en octets
            $table->boolean('main')->default(false); // image principale
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['annonce_id', 'type', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medias');
    }
};
