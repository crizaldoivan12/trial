<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'contact_number')) {
                $table->string('contact_number', 50)->nullable()->after('pay_claimant');
            }
            if (! Schema::hasColumn('documents', 'name_of_business')) {
                $table->string('name_of_business')->nullable()->after('contact_number');
            }
            if (! Schema::hasColumn('documents', 'reason')) {
                $table->text('reason')->nullable()->after('name_of_business');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'reason')) {
                $table->dropColumn('reason');
            }
            if (Schema::hasColumn('documents', 'name_of_business')) {
                $table->dropColumn('name_of_business');
            }
            if (Schema::hasColumn('documents', 'contact_number')) {
                $table->dropColumn('contact_number');
            }
        });
    }
};
