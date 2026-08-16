<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__site_audits', function (Blueprint $table) {
            // Apart van pages_crawled, want de deler van elke controle mag
            // alleen pagina's bevatten die echt zijn opgehaald, terwijl het
            // scherm wel moet kunnen tonen hoeveel er zijn afgeschermd.
            $table->unsignedInteger('pages_blocked')->default(0)->after('pages_crawled');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__site_audits', function (Blueprint $table) {
            $table->dropColumn('pages_blocked');
        });
    }
};
