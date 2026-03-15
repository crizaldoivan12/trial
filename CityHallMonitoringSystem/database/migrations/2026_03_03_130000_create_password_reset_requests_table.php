<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->text('requested_password');
            $table->unsignedSmallInteger('requested_password_length');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by_id')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('read_by_user_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_requests');
    }
};
