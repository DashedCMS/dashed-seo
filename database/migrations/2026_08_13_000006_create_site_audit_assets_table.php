<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audit_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__site_audits')->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 64);
            $table->string('type');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->boolean('compressed')->default(false);

            $table->unique(['audit_id', 'url_hash']);
            $table->index(['audit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audit_assets');
    }
};
