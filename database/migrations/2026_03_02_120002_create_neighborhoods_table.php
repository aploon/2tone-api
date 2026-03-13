<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->default('Bamako');
            $table->timestamps();

            $table->index(['city', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};
