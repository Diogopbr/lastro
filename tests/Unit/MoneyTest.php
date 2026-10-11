<?php

namespace Tests\Unit;

use App\Domain\Imports\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_parses_exact_integer_cents(string $text, int $cents): void
    {
        $this->assertSame($cents, Money::parse($text));
    }

    public static function validAmounts(): array
    {
        return [
            ['0.00', 0], ['0.01', 1], ['1.10', 110], ['1000000.00', 100000000],
            ['000001.23', 123], [str_repeat('0', 1000).'0.01', 1],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_or_excessive_amounts(string $text): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parse($text);
    }

    public static function invalidAmounts(): array
    {
        return array_map(fn (string $text): array => [$text], [
            '', '1', '1.0', '1.005', '.01', '1,00', '1e2', '1E2', '-1.00', '+1.00',
            '-0.00', '1000000.01', '10000000.00', str_repeat('9', 1000).'.00',
            ' 1.00', '1.00 ', "1.00\n", "1.00\0", '١.00', 'NaN', 'INF',
        ]);
    }

    #[DataProvider('formattedAmounts')]
    public function test_formats_without_float_including_entire_integer_range(int $cents, string $text): void
    {
        $this->assertSame($text, Money::format($cents));
    }

    public static function formattedAmounts(): array
    {
        return [
            [0, '0.00'], [1, '0.01'], [110, '1.10'], [-1, '-0.01'], [-123, '-1.23'],
            [100000000, '1000000.00'], [10000000000, '100000000.00'],
            [PHP_INT_MAX, '92233720368547758.07'], [PHP_INT_MIN, '-92233720368547758.08'],
        ];
    }

    public function test_sums_one_hundred_maximum_values_exactly(): void
    {
        $this->assertSame(8, PHP_INT_SIZE);
        $sum = array_sum(array_fill(0, 100, Money::parse('1000000.00')));
        $this->assertSame(10000000000, $sum);
        $this->assertSame('100000000.00', Money::format($sum));
    }
}
