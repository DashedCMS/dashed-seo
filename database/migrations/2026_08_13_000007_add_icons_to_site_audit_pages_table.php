<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__site_audit_pages', function (Blueprint $table) {
            $table->json('icons')->nullable()->after('scripts');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__site_audit_pages', function (Blueprint $table) {
            $table->dropColumn('icons');
        });
    }
};
