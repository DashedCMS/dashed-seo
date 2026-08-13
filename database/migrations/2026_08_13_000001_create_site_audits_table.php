<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audits', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('base_url');
            $table->string('status')->default('pending')->index();
            $table->string('trigger')->default('manual');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('urls_found')->default(0);
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('notice_count')->default(0);
            $table->json('settings')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audits');
    }
};
