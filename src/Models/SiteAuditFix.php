<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAuditFix extends Model
{
    public const MODE_AUTO = 'auto';
    public const MODE_SUGGESTION = 'suggestion';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERTED = 'reverted';
    public const STATUS_STALE = 'stale';

    protected $table = 'dashed__site_audit_fixes';

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'applied_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SiteAudit::class, 'audit_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SiteAuditPage::class, 'page_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * De vingerafdruk van de waarden die een fix aanraakt, op het moment van
     * voorstellen. Bij toepassen wordt hij opnieuw berekend; wijkt hij af, dan
     * heeft iemand het veld intussen zelf aangepast en mag de fix er niet
     * overheen.
     *
     * De sleutels worden recursief gesorteerd, zodat twee keer dezelfde inhoud
     * in een andere volgorde niet als een wijziging telt.
     */
    public static function fingerprintFor(array $values): string
    {
        $sorted = self::sortDeep($values);

        return hash('sha256', (string) json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected static function sortDeep(array $values): array
    {
        ksort($values);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::sortDeep($value);

                continue;
            }

            // Of PHP een int of een string teruggeeft voor een numerieke
            // kolom hangt af van de driverinstelling, dus zonder deze
            // gelijkschakeling kan dezelfde fix op de ene omgeving verlopen
            // en op de andere niet. Bewust alleen int en float: bool en null
            // meenemen zou false, null en lege tekst op elkaar laten botsen,
            // en dan valt een echte wijziging niet meer op.
            if (is_int($value) || is_float($value)) {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Bestaat er al een voorstel voor precies deze wijziging op dit record?
     *
     * Wachtend, afgewezen en teruggedraaid tellen mee. Afgewezen omdat je een
     * afwijzing niet elke nacht opnieuw wil wegklikken, teruggedraaid omdat
     * iemand die iets terugdraait daarmee zegt dat hij het niet wilde. Wat
     * wel is toegepast telt niet mee: staat de oude waarde er ooit weer, dan
     * is dat een nieuw probleem en hoort het voorstel terug te komen.
     */
    public static function alreadyKnown(
        string $checkKey,
        ?string $subjectType,
        ?string $subjectId,
        string $fingerprint,
    ): bool {
        return self::query()
            ->where('check_key', $checkKey)
            ->where('fingerprint', $fingerprint)
            ->when(
                $subjectType === null,
                fn ($query) => $query->whereNull('subject_type')->whereNull('subject_id'),
                fn ($query) => $query->where('subject_type', $subjectType)->where('subject_id', $subjectId),
            )
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_REJECTED, self::STATUS_REVERTED])
            ->exists();
    }
}
