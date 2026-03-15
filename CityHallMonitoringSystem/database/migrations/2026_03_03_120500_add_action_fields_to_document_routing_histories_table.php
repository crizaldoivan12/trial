<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_routing_histories', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('status');
            $table->string('action_taken', 30)->nullable()->after('remarks');
            $table->timestamp('action_at')->nullable()->after('action_taken');
            $table->timestamp('reviewed_at')->nullable()->after('action_at');
            $table->timestamp('signed_at')->nullable()->after('reviewed_at');
            $table->foreignId('action_by_id')->nullable()->after('signed_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('document_routing_histories', function (Blueprint $table) {
            $table->dropForeign(['action_by_id']);
            $table->dropColumn([
                'remarks',
                'action_taken',
                'action_at',
                'reviewed_at',
                'signed_at',
                'action_by_id',
            ]);
        });
    }
};
