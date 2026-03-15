<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'routed_department_id')) {
                $table->foreignId('routed_department_id')
                    ->nullable()
                    ->after('department_id')
                    ->constrained('departments');
            }
        });

        // Backfill existing rows to keep routing consistent.
        DB::table('documents')
            ->whereNull('routed_department_id')
            ->update([
                'routed_department_id' => DB::raw('department_id'),
            ]);

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'routed_department_id')) {
                $table->index('routed_department_id', 'documents_routed_department_perf_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'routed_department_id')) {
                $table->dropIndex('documents_routed_department_perf_index');
                $table->dropConstrainedForeignId('routed_department_id');
            }
        });
    }
};
