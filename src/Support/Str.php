<?php

namespace Neopayment\NboInstaller\Support;

class Str
{
    public static function kebab(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1-$2', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    public static function snake(string $value): string
    {
        return str_replace('-', '_', self::kebab($value));
    }

    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function title(string $value): string
    {
        return trim(ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}