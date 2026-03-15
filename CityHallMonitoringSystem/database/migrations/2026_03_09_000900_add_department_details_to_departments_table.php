<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'office')) {
                $table->string('office')->nullable()->after('code');
            }
            if (! Schema::hasColumn('departments', 'department_head')) {
                $table->string('department_head')->nullable()->after('office');
            }
            if (! Schema::hasColumn('departments', 'assistant_department_head')) {
                $table->string('assistant_department_head')->nullable()->after('department_head');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'assistant_department_head')) {
                $table->dropColumn('assistant_department_head');
            }
            if (Schema::hasColumn('departments', 'department_head')) {
                $table->dropColumn('department_head');
            }
            if (Schema::hasColumn('departments', 'office')) {
                $table->dropColumn('office');
            }
        });
    }
};
