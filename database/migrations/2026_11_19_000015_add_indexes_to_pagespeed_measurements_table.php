<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Sites waar 000014 op de te lange indexnaam vastliep, hebben de tabel zonder indexen.
     * Na een herhaalde run staat 000014 als gedaan geboekt, dus de indexen komen hier.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__pagespeed_measurements')) {
            return;
        }

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
    }
};
