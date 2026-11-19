<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__pagespeed_measurements')) {
            Schema::create('dashed__pagespeed_measurements', function (Blueprint $table) {
                $table->id();
                $table->string('site_id', 64);
                $table->string('url', 500);
                $table->string('strategy', 10);
                $table->unsignedTinyInteger('performance_score')->nullable();
                // Labdata van Lighthouse, altijd aanwezig bij een geslaagde meting.
                $table->unsignedInteger('lab_lcp_ms')->nullable();
                $table->unsignedInteger('lab_fcp_ms')->nullable();
                $table->unsignedInteger('lab_tbt_ms')->nullable();
                $table->float('lab_cls')->nullable();
                // Velddata uit CrUX, alleen bij genoeg echt verkeer op deze URL.
                $table->unsignedInteger('field_lcp_ms')->nullable();
                $table->unsignedInteger('field_inp_ms')->nullable();
                $table->float('field_cls')->nullable();
                $table->string('field_category', 20)->nullable();
                $table->text('error')->nullable();
                $table->timestamp('measured_at');
            });
        }

        // MySQL staat maximaal 64 tekens toe; de automatische naam was te lang. Een eerdere run
        // kan de tabel zonder indexen hebben achtergelaten, dus los toevoegen als ze ontbreken.
        Schema::table('dashed__pagespeed_measurements', function (Blueprint $table) {
            if (! Schema::hasIndex('dashed__pagespeed_measurements', 'dashed__psm_site_url_strategy_measured_index')) {
                $table->index(['site_id', 'url', 'strategy', 'measured_at'], 'dashed__psm_site_url_strategy_measured_index');
            }
            if (! Schema::hasIndex('dashed__pagespeed_measurements', 'dashed__psm_measured_at_index')) {
                $table->index('measured_at', 'dashed__psm_measured_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__pagespeed_measurements');
    }
};
