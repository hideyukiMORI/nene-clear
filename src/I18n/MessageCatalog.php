<?php

declare(strict_types=1);

namespace NeneClear\I18n;

final class MessageCatalog
{
    /** @var array<string, array<string, string>> */
    private array $loaded = [];

    public function __construct(private readonly string $langDir)
    {
    }

    public function get(string $key, Locale $locale): string
    {
        $messages = $this->load($locale);

        return $messages[$key] ?? $key;
    }

    /** @return array<string, string> */
    private function load(Locale $locale): array
    {
        if (!isset($this->loaded[$locale->value])) {
            $file = $this->langDir . '/' . $locale->value . '.php';
            $this->loaded[$locale->value] = is_file($file) ? (array) require $file : [];
        }

        return $this->loaded[$locale->value];
    }
}
