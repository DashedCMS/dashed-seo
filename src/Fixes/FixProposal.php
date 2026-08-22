<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes;

use Illuminate\Database\Eloquent\Model;

/**
 * Wat een fixer voorstelt te veranderen. Het subject mag null zijn bij een fix
 * die een record aanmaakt in plaats van wijzigt; dan blijft before leeg en
 * wordt het subject pas bij toepassen ingevuld.
 */
final class FixProposal
{
    public function __construct(
        public readonly ?Model $subject,
        public readonly ?string $locale,
        public readonly array $before,
        public readonly array $after,
        public readonly string $summary,
    ) {
    }
}
