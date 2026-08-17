<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__site_audit_pages')) {
            return;
        }

        if (Schema::hasColumn('dashed__site_audit_pages', 'from_cms')) {
            return;
        }

        Schema::table('dashed__site_audit_pages', function (Blueprint $table) {
            // Deze pagina komt uit de modellen van het CMS. Zonder dit vlaggetje
            // is een pagina die alleen daarvandaan komt niet te onderscheiden van
            // de startpagina: allebei zonder sitemap en zonder vindplaats.
            $table->boolean('from_cms')->default(false)->after('in_sitemap');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__site_audit_pages', 'from_cms')) {
            return;
        }

        Schema::table('dashed__site_audit_pages', function (Blueprint $table) {
            $table->dropColumn('from_cms');
        });
    }
};
