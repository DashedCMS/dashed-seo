<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Contracts;

use Dashed\DashedSeo\Models\SiteAudit;

interface SeoCheck
{
    /** Vaste sleutel, bijvoorbeeld 'duplicate_title'. */
    public function key(): string;

    /** Sectie in het rapport, bijvoorbeeld 'meta_tags'. */
    public function section(): string;

    /** 'error', 'warning' of 'notice'. */
    public function severity(): string;

    public function label(): string;

    public function description(): string;

    public function howToFix(): string;

    /** @return iterable<\Dashed\DashedSeo\Checks\Finding> */
    public function run(SiteAudit $audit): iterable;
}
