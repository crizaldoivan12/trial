<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_routing_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments');
            $table->foreignId('to_department_id')->constrained('departments');
            $table->foreignId('routed_by_id')->nullable()->constrained('users');
            $table->string('status', 50)->nullable();
            $table->timestamp('routed_at')->useCurrent();
            $table->timestamps();

            $table->index(['document_id', 'routed_at'], 'doc_routing_history_document_time_idx');
            $table->index(['to_department_id', 'routed_at'], 'doc_routing_history_to_dept_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_routing_histories');
    }
};
