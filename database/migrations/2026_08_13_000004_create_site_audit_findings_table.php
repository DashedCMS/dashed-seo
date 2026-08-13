<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__site_audits')->cascadeOnDelete();
            $table->string('check_key')->index();
            $table->string('section');
            $table->string('severity');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->text('url')->nullable();
            $table->json('context')->nullable();
            $table->boolean('is_new')->default(false);

            $table->index(['audit_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audit_findings');
    }
};
