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
        $key = $check->key();

        if (isset($this->checks[$key])) {
            throw new \RuntimeException(sprintf(
                "Controle-sleutel '%s' is al geregistreerd door %s en kan niet opnieuw geregistreerd worden door %s.",
                $key,
                $this->checks[$key]::class,
                $check::class,
            ));
        }

        $this->checks[$key] = $check;
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
