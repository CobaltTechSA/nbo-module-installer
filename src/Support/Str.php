<?php

namespace Neopayment\NboInstaller\Support;

class Str
{
    /**
     * Converts a given string to kebab-case, where words are separated by hyphens (-).
     *
     * @param string $value The input string to be converted to kebab-case.
     * @return string The converted kebab-case string.
     */
    public static function kebab(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1-$2', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    /**
     * Converts a given string from kebab-case to snake_case.
     *
     * @param string $value The input string in kebab-case format.
     * @return string The converted string in snake_case format.
     */
    public static function snake(string $value): string
    {
        return str_replace('-', '_', self::kebab($value));
    }

    /**
     * Converts a string into StudlyCase format by replacing hyphens and underscores
     * with spaces, capitalizing the first letter of each word, and removing the spaces.
     *
     * @param string $value The input string to be transformed.
     * @return string The StudlyCase version of the input string.
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Converts a string into a title-cased format by replacing hyphens and underscores
     * with spaces, and capitalizing the first letter of each word.
     *
     * @param string $value The input string to be converted.
     * @return string The title-cased version of the input string.
     */
    public static function title(string $value): string
    {
        return trim(ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}