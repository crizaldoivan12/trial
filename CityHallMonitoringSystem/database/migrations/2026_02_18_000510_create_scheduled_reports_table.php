<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('frequency')->default('monthly'); // future-proof
            $table->string('email')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->string('last_report_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};

