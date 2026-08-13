<?php

declare(strict_types=1);

namespace Dashed\DashedSeo;

use Dashed\DashedSeo\Contracts\SeoCheck;

class SeoCheckRegistry
{
    /** @var array<string, SeoCheck> */
    protected array $checks = [];

    public function register(SeoCheck $check): void
    {
        $this->checks[$check->key()] = $check;
    }

    /** @return array<int, SeoCheck> */
    public function checks(): array
    {
        return array_values($this->checks);
    }

    public function check(string $key): ?SeoCheck
    {
        return $this->checks[$key] ?? null;
    }

    public function flush(): void
    {
        $this->checks = [];
    }
}
