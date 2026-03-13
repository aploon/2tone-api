<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codes OTP pour inscription / connexion par SMS.
     */
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('telephone', 20);
            $table->string('code', 10);
            $table->timestamp('expiration');
            $table->boolean('valide')->default(true);
            $table->timestamps();

            $table->index(['telephone', 'valide', 'expiration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
