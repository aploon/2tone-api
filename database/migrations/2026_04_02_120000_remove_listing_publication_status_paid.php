<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le statut de publication « paid » est supprimé : après paiement, l’annonce est en « pending ».
     */
    public function up(): void
    {
        DB::table('listings')
            ->where('publication_status', 'paid')
            ->update(['publication_status' => 'pending', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Non rejouable sans savoir quelles lignes étaient « paid » avant migration.
    }
};
