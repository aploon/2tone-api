<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages de contact locataire -> propriétaire (via annonce).
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annonce_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('date')->useCurrent();
            $table->timestamps();

            $table->index(['annonce_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
