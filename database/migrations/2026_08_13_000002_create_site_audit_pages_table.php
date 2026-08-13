<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audit_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('dashed__site_audits')->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 64);
            $table->text('path')->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('crawl_status')->default('pending');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedBigInteger('discovered_from_page_id')->nullable();
            $table->boolean('in_sitemap')->default(false);
            $table->boolean('blocked_by_robots')->default(false);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('redirect_target')->nullable();
            $table->json('redirect_chain')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('content_type')->nullable();
            $table->string('content_encoding')->nullable();
            $table->unsignedInteger('html_size')->nullable();
            $table->text('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical')->nullable();
            $table->string('robots_meta')->nullable();
            $table->string('html_lang', 20)->nullable();
            $table->json('h1s')->nullable();
            $table->json('heading_counts')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->json('hreflangs')->nullable();
            $table->json('images')->nullable();
            $table->json('stylesheets')->nullable();
            $table->json('scripts')->nullable();
            $table->boolean('indexable')->default(true);
            $table->string('model_type')->nullable();
            $table->string('model_id')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->text('fetch_error')->nullable();
            $table->timestamps();

            $table->unique(['audit_id', 'url_hash']);
            $table->index(['audit_id', 'crawl_status']);
            $table->index(['audit_id', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audit_pages');
    }
};
