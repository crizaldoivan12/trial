<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('inactive_alerted_at')->nullable()->after('date_out');
            $table->timestamp('inactive_read_at')->nullable()->after('inactive_alerted_at');
            $table->text('inactive_reason')->nullable()->after('inactive_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['inactive_alerted_at', 'inactive_read_at', 'inactive_reason']);
        });
    }
};
