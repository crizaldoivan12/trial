<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Account approval lifecycle status:
            // Pending -> Approved OR Rejected
            // Approved -> Deactivated (and back to Approved when reactivated)
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->string('account_status')->default('Pending')->after('is_active')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'account_status')) {
                $table->dropIndex(['account_status']);
                $table->dropColumn('account_status');
            }
        });
    }
};

