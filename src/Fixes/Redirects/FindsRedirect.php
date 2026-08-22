<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Redirects;

use Dashed\DashedCore\Models\Redirect;

trait FindsRedirect
{
    /**
     * De redirect in het CMS die bij dit adres hoort. Null betekent dat de
     * doorverwijzing ergens anders vandaan komt (nginx, een pakket), en dan is
     * er vanuit de beheeromgeving niets aan te doen.
     */
    protected function redirectFor(string $url): ?Redirect
    {
        $pad = $this->path($url);

        return Redirect::query()
            ->whereIn('from', array_unique([$pad, ltrim($pad, '/'), $pad . '/']))
            ->first();
    }
}
