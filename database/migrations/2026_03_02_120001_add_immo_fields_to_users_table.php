<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs Immo-3D Mali : utilisateur = téléphone unique, rôle (locataire/propriétaire/admin).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 20)->unique()->nullable()->after('id');
            $table->string('numero_whatsapp', 20)->unique()->nullable()->after('telephone');
            $table->string('nom', 100)->nullable()->after('numero_whatsapp');
            $table->string('role', 20)->default('locataire')->after('nom'); // locataire | proprietaire | admin
            $table->string('statut', 20)->default('actif')->after('role'); // actif | suspendu
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telephone', 'numero_whatsapp', 'nom', 'role', 'statut']);
        });
    }
};
