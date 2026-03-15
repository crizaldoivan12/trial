<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Speed up common filters and dashboard queries.
            $table->index('status', 'documents_status_perf_index');
            $table->index('encoded_by_id', 'documents_encoded_by_perf_index');
            $table->index('department_id', 'documents_department_perf_index');
            $table->index('date', 'documents_date_perf_index');
            $table->index('created_at', 'documents_created_at_perf_index');

            // Combined index used by encoder inactivity checks and "My Documents".
            $table->index(
                ['encoded_by_id', 'status', 'updated_at'],
                'documents_encoded_status_updated_perf_index'
            );
        });

        Schema::table('edit_requests', function (Blueprint $table) {
            // Optimize notifications and edit-request lookups.
            $table->index('status', 'edit_requests_status_perf_index');
            $table->index('requested_by_user_id', 'edit_requests_requested_by_perf_index');
            $table->index('requested_to_user_id', 'edit_requests_requested_to_perf_index');
            $table->index('document_id', 'edit_requests_document_perf_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_status_perf_index');
            $table->dropIndex('documents_encoded_by_perf_index');
            $table->dropIndex('documents_department_perf_index');
            $table->dropIndex('documents_date_perf_index');
            $table->dropIndex('documents_created_at_perf_index');
            $table->dropIndex('documents_encoded_status_updated_perf_index');
        });

        Schema::table('edit_requests', function (Blueprint $table) {
            $table->dropIndex('edit_requests_status_perf_index');
            $table->dropIndex('edit_requests_requested_by_perf_index');
            $table->dropIndex('edit_requests_requested_to_perf_index');
            $table->dropIndex('edit_requests_document_perf_index');
        });
    }
};

