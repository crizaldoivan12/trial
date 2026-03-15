<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // Core document fields
            $table->date('date');
            $table->foreignId('encoded_by_id')->constrained('users');
            $table->string('type_of_document');

            // Auto-generated, non-editable, indexed document code (CH-YYYY-DEPT-XXXX)
            $table->string('document_code')->unique()->index();

            $table->string('document_number')->nullable();
            $table->string('pay_claimant');
            $table->text('particular');
            $table->decimal('amount', 15, 2);

            // Requesting department
            $table->foreignId('department_id')->constrained('departments');

            // Status / flow tracking
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->date('date_out')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

