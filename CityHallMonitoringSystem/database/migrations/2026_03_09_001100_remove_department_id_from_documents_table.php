<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
public function up(): void
{
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        return;
    }

    if (! Schema::hasColumn('documents', 'department_id')) {
        return;
    }

    Schema::table('documents', function (Blueprint $table) {
        $table->dropConstrainedForeignId('department_id');
    });
}
    public function down(): void
    {
        if (Schema::hasColumn('documents', 'department_id')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->after('amount');
            $table->index('department_id');
            $table->index(['department_id', 'status']);
        });
    }
};
