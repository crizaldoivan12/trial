<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Documents: common filter/sort columns
        Schema::table('documents', function (Blueprint $table) {
            $table->index('encoded_by_id');
            $table->index('status');
            $table->index('department_id');
            $table->index('date');
            $table->index('type_of_document');

            // Composite indexes for typical UI filters
            $table->index(['department_id', 'status']);
            $table->index(['encoded_by_id', 'date']);
        });

        // Departments: active filter + sorting
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'is_active')) {
                $table->index('is_active');
            }
        });

        // Users: active filter
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->index('is_active');
            }
        });

        // Audit trails: common queries (latest activity)
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['encoded_by_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['date']);
            $table->dropIndex(['type_of_document']);
            $table->dropIndex(['department_id', 'status']);
            $table->dropIndex(['encoded_by_id', 'date']);
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'is_active')) {
                $table->dropIndex(['is_active']);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropIndex(['is_active']);
            }
        });

        Schema::table('audit_trails', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropIndex(['created_at']);
        });
    }
};

