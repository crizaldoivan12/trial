<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('edit_requests', function (Blueprint $table) {
            $table->timestamp('read_by_requested_to_at')->nullable()->after('expires_at');
            $table->timestamp('read_by_requested_by_at')->nullable()->after('read_by_requested_to_at');
            $table->timestamp('read_by_admin_at')->nullable()->after('read_by_requested_by_at');
        });
    }

    public function down(): void
    {
        Schema::table('edit_requests', function (Blueprint $table) {
            $table->dropColumn([
                'read_by_requested_to_at',
                'read_by_requested_by_at',
                'read_by_admin_at',
            ]);
        });
    }
};
