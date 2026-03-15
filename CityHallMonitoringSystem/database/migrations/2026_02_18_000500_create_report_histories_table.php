<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_histories', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // filtered_data, summary, department, monthly, scheduled
            $table->string('format'); // excel, pdf
            $table->json('filters')->nullable();
            $table->foreignId('generated_by_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_histories');
    }
};

