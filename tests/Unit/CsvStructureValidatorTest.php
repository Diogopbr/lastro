<?php

namespace Tests\Unit;

use App\Domain\Imports\CsvStructureValidator;
use App\Domain\Imports\CsvValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvStructureValidatorTest extends TestCase
{
    #[DataProvider('validStructures')]
    public function test_tracks_width_and_physical_record_start(string $bytes, array $expected): void
    {
        $this->assertSame($expected, (new CsvStructureValidator)->inspect($bytes));
    }

    public static function validStructures(): array
    {
        return [
            ['a,b', [['line' => 1, 'fieldCount' => 2]]],
            ["a,b\n1,2\n", [['line' => 1, 'fieldCount' => 2], ['line' => 2, 'fieldCount' => 2]]],
            ["\xEF\xBB\xBFa,b\r\n1,2\r\n", [['line' => 1, 'fieldCount' => 2], ['line' => 2, 'fieldCount' => 2]]],
            ["a,b\n\"one,\n\"\"two\"\"\",2\n3,4", [
                ['line' => 1, 'fieldCount' => 2], ['line' => 2, 'fieldCount' => 2], ['line' => 4, 'fieldCount' => 2],
            ]],
            ['"",,', [['line' => 1, 'fieldCount' => 3]]],
            ['"a","b""c"', [['line' => 1, 'fieldCount' => 2]]],
            ["a,b\r\n1,2\n3,4", [
                ['line' => 1, 'fieldCount' => 2], ['line' => 2, 'fieldCount' => 2], ['line' => 3, 'fieldCount' => 2],
            ]],
        ];
    }

    #[DataProvider('invalidStructures')]
    public function test_rejects_structure_explicitly_at_record_start(string $bytes, int $line, string $code): void
    {
        try {
            (new CsvStructureValidator)->inspect($bytes);
            $this->fail('Invalid CSV was accepted.');
        } catch (CsvValidationException $exception) {
            $this->assertSame($line, $exception->recordLine);
            $this->assertSame($code, $exception->errorCode);
            $this->assertNotSame('', $exception->getMessage());
        }
    }

    public static function invalidStructures(): array
    {
        return [
            ['', 1, 'empty_file'], ["\xEF\xBB\xBF", 1, 'empty_file'],
            ["a,b\n\n1,2", 2, 'blank_record'], ["a,b\n1,2\n\n", 3, 'blank_record'],
            ["a,b\n\"open,2", 2, 'invalid_quotes'],
            ["a,b\nbad\"quote,2", 2, 'invalid_quotes'],
            ["a,b\n\"closed\"junk,2", 2, 'invalid_quotes'],
            ["a,b\n \"quoted\",2", 2, 'invalid_quotes'],
            ["a,b\n\"quoted\" ,2", 2, 'invalid_quotes'],
            ["a,b\n\"escaped\\\"quote\",2", 2, 'invalid_quotes'],
            ["a,b\r1,2", 1, 'invalid_newline'],
            ["a,b\n1,\0", 2, 'invalid_control'],
            ["a,b\n1,\xFF", 2, 'invalid_utf8'],
            [str_repeat('a', 1048577), 1, 'file_too_large'],
        ];
    }

    public function test_accepts_exact_byte_limit(): void
    {
        $this->assertSame([['line' => 1, 'fieldCount' => 1]], (new CsvStructureValidator)->inspect(str_repeat('a', 1048576)));
    }
}
