<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__site_audit_fixes', function (Blueprint $table) {
            $table->id();
            // Bewust nullOnDelete en geen cascade: AuditPruner ruimt oude scans
            // op, en met een cascade zou het logboek van wat er in de site is
            // veranderd daarmee verdwijnen. De scan mag weg, de vastlegging niet.
            $table->foreignId('audit_id')->nullable()->constrained('dashed__site_audits')->nullOnDelete();
            $table->string('site_id')->nullable();
            $table->string('check_key');
            $table->unsignedBigInteger('page_id')->nullable();
            // De bevinding waar dit voorstel uit voortkomt. Geen foreign key:
            // bevindingen verdwijnen met hun scan mee en dit logboek blijft
            // staan, net als audit_id dat doet. Nodig omdat een pagina
            // meerdere bevindingen van dezelfde controle kan hebben (drie
            // kapotte links op een pagina), en het rapport anders elke regel
            // aan hetzelfde voorstel koppelt. Leeg bij een sitebrede fixer:
            // die dekt alle bevindingen van zijn controle in een keer.
            $table->unsignedBigInteger('finding_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('mode');
            $table->string('status')->default('pending');
            $table->text('summary')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('fingerprint', 64);
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['subject_type', 'subject_id', 'check_key']);
            $table->index(['audit_id', 'check_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__site_audit_fixes');
    }
};
