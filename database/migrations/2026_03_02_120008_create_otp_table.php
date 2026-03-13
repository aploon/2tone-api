<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('telephone', 20);
            $table->string('code', 10);
            $table->timestamp('expires_at');
            $table->boolean('is_valid')->default(true);
            $table->timestamps();

            $table->index(['telephone', 'is_valid', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
