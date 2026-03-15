<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action'); // e.g. document_created, document_updated, department_created, login
            $table->foreignId('document_id')->nullable()->constrained('documents');
            $table->json('payload')->nullable(); // Stores before/after snapshots or metadata
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};

