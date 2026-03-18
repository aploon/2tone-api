<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('contacted_at')->useCurrent();
            $table->timestamps();

            $table->index(['listing_id', 'contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
