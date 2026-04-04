<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('cinetpay_notify_token', 255)->nullable()->after('fedapay_transaction_id');
            $table->string('cinetpay_transaction_id', 120)->nullable()->after('cinetpay_notify_token');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['cinetpay_notify_token', 'cinetpay_transaction_id']);
        });
    }
};
