<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Users: active flag
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('Encoder');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }
        });

        // Departments: active flag
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('code');
            }
        });

        // Documents: soft deletes and unique document_number
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'deleted_at')) {
                $table->softDeletes();
            }
            // Prevent duplicate manual document numbers (allow multiple NULLs)
            if (!Schema::hasColumn('documents', 'document_number')) {
                $table->string('document_number')->nullable();
            }
            $table->unique('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            $table->dropUnique(['document_number']);
        });
    }
};

