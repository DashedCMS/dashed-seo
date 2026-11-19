<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__search_console_rows')) {
            return;
        }

        // Eén rij per site, dag, pagina en zoekterm, zoals Search Console hem
        // teruggeeft. Dat is al een aggregaat (Google telt per dag op), dus
        // er is geen aparte dagtabel nodig.
        Schema::create('dashed__search_console_rows', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 64);
            $table->date('date');
            $table->text('page');
            $table->string('page_path', 500);
            $table->string('search_query', 500);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->float('ctr')->default(0);
            $table->float('position')->default(0);

            $table->index(['site_id', 'date']);
            $table->index(['site_id', 'page_path', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__search_console_rows');
    }
};
