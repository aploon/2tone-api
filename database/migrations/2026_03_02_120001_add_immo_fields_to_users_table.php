<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 20)->unique()->nullable()->after('id');
            $table->string('whatsapp_number', 20)->unique()->nullable()->after('telephone');
            $table->string('role', 20)->default('tenant')->after('whatsapp_number');
            $table->string('status', 20)->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telephone', 'whatsapp_number', 'role', 'status']);
        });
    }
};
