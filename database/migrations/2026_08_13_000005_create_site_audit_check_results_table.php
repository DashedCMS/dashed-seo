<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audit_check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__site_audits')->cascadeOnDelete();
            $table->string('check_key');
            $table->string('section');
            $table->string('severity');
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('resolved_count')->default(0);

            $table->unique(['audit_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audit_check_results');
    }
};
