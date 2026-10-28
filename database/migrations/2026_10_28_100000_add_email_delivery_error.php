<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_email_deliveries', function (Blueprint $table) {
            $table->string('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_email_deliveries', fn (Blueprint $table) => $table->dropColumn('last_error'));
    }
};
