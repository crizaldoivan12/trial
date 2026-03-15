<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents');
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->foreignId('requested_to_user_id')->constrained('users');
            $table->string('status')->default('pending'); // pending, accepted, rejected, expired, used
            $table->text('remarks')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edit_requests');
    }
};

