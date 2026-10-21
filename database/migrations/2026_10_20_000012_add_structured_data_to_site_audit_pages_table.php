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

        if (Schema::hasColumn('dashed__site_audit_pages', 'structured_data')) {
            return;
        }

        Schema::table('dashed__site_audit_pages', function (Blueprint $table) {
            // Per JSON-LD-blok op de pagina: geldig of niet, en per knoop het
            // type en de gevulde eigenschappen (StructuredData::summarize()).
            // De ruwe HTML wordt na het crawlen weggegooid, dus wat de
            // schemacontroles nodig hebben moet hier staan.
            $table->json('structured_data')->nullable()->after('icons');
            $table->boolean('non_crawlable_pagination')->default(false)->after('structured_data');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__site_audit_pages', 'structured_data')) {
            return;
        }

        Schema::table('dashed__site_audit_pages', function (Blueprint $table) {
            $table->dropColumn(['structured_data', 'non_crawlable_pagination']);
        });
    }
};
