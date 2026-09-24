<?php

namespace Neopayment\NboInstaller\Tests\Support;

use Neopayment\NboInstaller\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    #[DataProvider('separatedNames')]
    public function testKebab(string $input, string $expected): void
    {
        self::assertSame($expected, Str::kebab($input));
    }

    #[DataProvider('separatedNames')]
    public function testSnake(string $input, string $expected): void
    {
        self::assertSame(str_replace('-', '_', $expected), Str::snake($input));
    }

    public static function separatedNames(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'simple' => ['customers', 'customers'];
        yield 'camel case' => ['customerOrders', 'customer-orders'];
        yield 'pascal case' => ['CustomerOrders', 'customer-orders'];
        yield 'uppercase' => ['CUSTOMER_ORDERS', 'customer-orders'];
        yield 'mixed separators' => ['  customer__orders / items!! ', 'customer-orders-items'];
        yield 'numbers' => ['orders2026', 'orders2026'];
        yield 'punctuation only' => [' _ / ! ', ''];
    }

    #[DataProvider('displayNames')]
    public function testStudlyAndTitle(string $input, string $studly, string $title): void
    {
        self::assertSame($studly, Str::studly($input));
        self::assertSame($title, Str::title($input));
    }

    public static function displayNames(): iterable
    {
        yield 'empty' => ['', '', ''];
        yield 'kebab' => ['customer-orders', 'CustomerOrders', 'Customer Orders'];
        yield 'snake' => ['customer_orders', 'CustomerOrders', 'Customer Orders'];
        yield 'spaces' => [' customer orders ', 'CustomerOrders', 'Customer Orders'];
        yield 'camel case preserved' => ['customerOrders', 'CustomerOrders', 'CustomerOrders'];
    }

    public function testUpper(): void
    {
        self::assertSame('CUSTOMER_ORDERS_2026', Str::upper('customer_orders_2026'));
        self::assertSame('', Str::upper(''));
    }
}
