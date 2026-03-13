<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', [
                'Villa',
                'Maison',
                'Appartement',
                'Duplex/Triplex',
                'Immeuble',
                'Studio',
                'Bureau',
                'Terrain',
            ]);
            $table->unsignedInteger('price');
            $table->string('publication_status', 30)->default('draft');
            $table->unsignedTinyInteger('bedrooms')->default(0);
            $table->unsignedTinyInteger('bathrooms')->default(0);
            $table->unsignedInteger('surface_sqm')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index(['publication_status', 'created_at']);
            $table->index('neighborhood_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
