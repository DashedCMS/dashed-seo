<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audit_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__site_audits')->cascadeOnDelete();
            $table->unsignedBigInteger('from_page_id');
            $table->text('to_url');
            $table->string('to_url_hash', 64);
            $table->unsignedBigInteger('to_page_id')->nullable();
            $table->text('anchor')->nullable();
            $table->string('rel')->nullable();
            $table->boolean('is_external')->default(false);
            $table->boolean('is_image_link')->default(false);

            $table->index(['audit_id', 'to_page_id']);
            $table->index(['audit_id', 'from_page_id']);
            $table->index(['audit_id', 'to_url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audit_links');
    }
};
