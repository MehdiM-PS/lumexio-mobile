<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_state', function (Blueprint $table) {
            $table->string('active_shop_name')->nullable();
            $table->unsignedInteger('unread_alert_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('local_state', function (Blueprint $table) {
            $table->dropColumn(['active_shop_name', 'unread_alert_count']);
        });
    }
};
