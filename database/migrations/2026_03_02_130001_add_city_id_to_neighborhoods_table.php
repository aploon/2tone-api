<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $cityColumnExists = Schema::hasColumn('neighborhoods', 'city');
        if ($cityColumnExists) {
            $bamakoId = DB::table('cities')->where('name', 'Bamako')->value('id');
            if (! $bamakoId) {
                $bamakoId = DB::table('cities')->insertGetId(['name' => 'Bamako', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('neighborhoods')->update(['city_id' => $bamakoId]);
            Schema::table('neighborhoods', function (Blueprint $table) {
                $table->dropIndex(['city', 'name']);
            });
Schema::table('neighborhoods', function (Blueprint $table) {
                $table->dropColumn('city');
            });
        }

        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->index(['city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropIndex(['city_id', 'name']);
        });

        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->string('city')->default('Bamako')->after('name');
            $table->index(['city', 'name']);
        });

        DB::table('neighborhoods')->update(['city' => 'Bamako']);
    }
};
