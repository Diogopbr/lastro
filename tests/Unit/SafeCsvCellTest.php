<?php

namespace Tests\Unit;

use App\Domain\Exports\SafeCsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeCsvCellTest extends TestCase
{
    #[DataProvider('cells')]
    public function test_guards_only_formula_like_free_text(string $input, string $expected): void
    {
        $this->assertSame($expected, SafeCsvCell::text($input));
    }

    public static function cells(): array
    {
        return [
            ['=HYPERLINK("x")', '\'=HYPERLINK("x")'], ['+1', "'+1"], ['-10.00', "'-10.00"], ['@SUM(1)', "'@SUM(1)"],
            [" \t=1", "' \t=1"], ["\r\n=1", "'\r\n=1"], ["\0+1", "'\0+1"],
            ["\u{00A0}@x", "'\u{00A0}@x"], ["\u{FEFF}=x", "'\u{FEFF}=x"],
            ['', ''], ['0001', '0001'], ['ab,"c"', 'ab,"c"'], ['a=1', 'a=1'], ["\ttexto", "\ttexto"],
        ];
    }
}
