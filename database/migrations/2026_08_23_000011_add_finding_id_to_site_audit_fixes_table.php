<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // De kolom staat ook in de create-migratie hierboven, zodat een verse
        // installatie hem sowieso krijgt. Deze losse migratie is er voor de
        // omgevingen die de create al gedraaid hebben.
        if (Schema::hasColumn('dashed__site_audit_fixes', 'finding_id')) {
            return;
        }

        Schema::table('dashed__site_audit_fixes', function (Blueprint $table) {
            $table->unsignedBigInteger('finding_id')->nullable()->after('page_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__site_audit_fixes', 'finding_id')) {
            return;
        }

        Schema::table('dashed__site_audit_fixes', function (Blueprint $table) {
            $table->dropColumn('finding_id');
        });
    }
};
