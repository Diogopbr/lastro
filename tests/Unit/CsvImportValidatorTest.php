<?php

namespace Tests\Unit;

use App\Domain\Imports\CsvImportValidator;
use App\Domain\Imports\ImportPreview;
use App\Domain\Imports\ImportRow;
use App\Domain\Imports\Money;
use App\Domain\Imports\SourceKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvImportValidatorTest extends TestCase
{
    private const ORDERS = 'order_id,ordered_at,gross_amount,currency';

    private const PAYOUTS = 'order_id,payout_id,payout_date,gross_amount,fee_amount,net_amount,currency';

    private function validate(string $bytes, string $kind = 'orders'): ImportPreview
    {
        return (new CsvImportValidator)->validate(
            SourceKind::from($kind), $bytes, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'),
        );
    }

    public function test_preserves_identifiers_and_projects_decimal_strings(): void
    {
        $preview = $this->validate(file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'csv'.DIRECTORY_SEPARATOR.'orders-valid.csv'));
        $this->assertTrue($preview->valid);
        $this->assertSame('orders', SourceKind::Orders->value);
        $this->assertSame('payouts', SourceKind::Payouts->value);
        $this->assertSame(3, $preview->rowCount);
        $this->assertSame('3.33', $preview->grossTotal);
        $this->assertSame(333, $preview->grossTotalCents);
        $this->assertSame([], $preview->errors);
        $this->assertSame([
            ['line' => 2, 'orderId' => '0001', 'payoutId' => null, 'businessDate' => '2026-01-01', 'gross' => '1.10', 'fee' => null, 'net' => null],
            ['line' => 3, 'orderId' => '1', 'payoutId' => null, 'businessDate' => '2026-12-31', 'gross' => '2.22', 'fee' => null, 'net' => null],
            ['line' => 4, 'orderId' => 'Ab,"C"', 'payoutId' => null, 'businessDate' => '2026-06-01', 'gross' => '0.01', 'fee' => null, 'net' => null],
        ], $preview->toArray()['rows']);
        $this->assertSame(110, $preview->rows[0]->grossCents);
        $this->assertSame($preview->toArray(), json_decode(json_encode($preview, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['valid', 'normalizedHash', 'rowCount', 'grossTotal', 'rows', 'errors'], array_keys($preview->toArray()));
    }

    public function test_payouts_allow_zero_fee_net_and_repeated_payout_id(): void
    {
        $preview = $this->validate(file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'csv'.DIRECTORY_SEPARATOR.'payouts-valid.csv'), 'payouts');
        $this->assertTrue($preview->valid);
        $this->assertSame('20.00', $preview->grossTotal);
        $this->assertSame(0, $preview->rows[0]->feeCents);
        $this->assertSame(0, $preview->rows[1]->netCents);
        $this->assertSame('000P', $preview->rows[0]->payoutId);
        $this->assertSame('000P', $preview->rows[1]->payoutId);
        $this->assertSame('0.00', $preview->toArray()['rows'][1]['net']);
    }

    #[DataProvider('invalidImports')]
    public function test_rejects_invalid_imports_with_field_and_line(string $bytes, string $kind, int $line, string $field, string $code): void
    {
        $preview = $this->validate($bytes, $kind);
        $this->assertFalse($preview->valid);
        $this->assertNull($preview->normalizedHash);
        $matches = array_filter($preview->errors, fn (array $error): bool => $error['line'] === $line && $error['field'] === $field && $error['code'] === $code);
        $this->assertNotEmpty($matches, json_encode($preview->errors, JSON_THROW_ON_ERROR));
        foreach ($preview->errors as $error) {
            $this->assertSame(['line', 'field', 'code', 'message'], array_keys($error));
            $this->assertNotSame('', $error['message']);
        }
    }

    public static function invalidImports(): array
    {
        $orders = self::ORDERS."\n";
        $payouts = self::PAYOUTS."\n";

        return [
            ['', 'orders', 1, 'file', 'empty_file'],
            [self::ORDERS, 'orders', 1, 'file', 'record_count'],
            [$orders.'1,2026-01-01,1.00,BRL,extra', 'orders', 2, 'record', 'field_count'],
            [$orders.'1,2026-01-01,1.00', 'orders', 2, 'record', 'field_count'],
            [$orders.self::ORDERS, 'orders', 2, 'record', 'repeated_header'],
            ["ordered_at,order_id,gross_amount,currency\n2026-01-01,1,1.00,BRL", 'orders', 1, 'header', 'invalid_header'],
            ["order_id,ordered_at,gross_amount,Currency\n1,2026-01-01,1.00,BRL", 'orders', 1, 'header', 'invalid_header'],
            ["order_id,ordered_at,gross_amount,currency \n1,2026-01-01,1.00,BRL", 'orders', 1, 'header', 'invalid_header'],
            [$payouts.'1,p,2026-01-01,1.00,0.00,1.00,BRL', 'orders', 1, 'header', 'invalid_header'],
            [$orders."1,2026-01-01,1.00,BRL\n1,2026-01-02,2.00,BRL", 'orders', 3, 'order_id', 'duplicate_order_id'],
            [$orders.',2026-01-01,1.00,BRL', 'orders', 2, 'order_id', 'required'],
            [$orders.'1,,1.00,BRL', 'orders', 2, 'ordered_at', 'required'],
            [$orders.'1,2026-01-01,,BRL', 'orders', 2, 'gross_amount', 'required'],
            [$orders.'1,2026-01-01,1.00,', 'orders', 2, 'currency', 'required'],
            [$payouts.'1,,2026-01-01,1.00,0.00,1.00,BRL', 'payouts', 2, 'payout_id', 'required'],
            [$payouts.'1,p,,1.00,0.00,1.00,BRL', 'payouts', 2, 'payout_date', 'required'],
            [$payouts.'1,p,2026-01-01,1.00,,1.00,BRL', 'payouts', 2, 'fee_amount', 'required'],
            [$payouts.'1,p,2026-01-01,1.00,0.00,,BRL', 'payouts', 2, 'net_amount', 'required'],
            [$orders.'1,2026-02-30,1.00,BRL', 'orders', 2, 'ordered_at', 'invalid_date'],
            [$orders.'1,2026-02-29,1.00,BRL', 'orders', 2, 'ordered_at', 'invalid_date'],
            [$orders.'1,2026-1-01,1.00,BRL', 'orders', 2, 'ordered_at', 'invalid_date'],
            [$orders.'1,2025-12-31,1.00,BRL', 'orders', 2, 'ordered_at', 'outside_period'],
            [$orders.'1,2027-01-01,1.00,BRL', 'orders', 2, 'ordered_at', 'outside_period'],
            [$payouts.'1,p,2025-12-31,1.00,0.00,1.00,BRL', 'payouts', 2, 'payout_date', 'outside_period'],
            [$orders.'1,2026-01-01,1.00,USD', 'orders', 2, 'currency', 'invalid_currency'],
            [$orders.'1,2026-01-01,1.00,brl', 'orders', 2, 'currency', 'invalid_currency'],
            [$orders.'1,2026-01-01,0.00,BRL', 'orders', 2, 'gross_amount', 'gross_not_positive'],
            [$payouts.'1,p,2026-01-01,0.00,0.00,0.00,BRL', 'payouts', 2, 'gross_amount', 'gross_not_positive'],
            [$orders.'1,2026-01-01,1000000.01,BRL', 'orders', 2, 'gross_amount', 'invalid_money'],
            [$orders.'1,2026-01-01,1e2,BRL', 'orders', 2, 'gross_amount', 'invalid_money'],
            [$orders.'1,2026-01-01,1.005,BRL', 'orders', 2, 'gross_amount', 'invalid_money'],
            [$payouts.'1,p,2026-01-01,1.00,1.01,0.00,BRL', 'payouts', 2, 'fee_amount', 'fee_exceeds_gross'],
            [$payouts.'1,p,2026-01-01,1.00,0.10,0.89,BRL', 'payouts', 2, 'net_amount', 'net_mismatch'],
            [$payouts.'1,p,2026-01-01,1.00,-0.01,1.01,BRL', 'payouts', 2, 'fee_amount', 'invalid_money'],
            [$payouts.'1,p,2026-01-01,1.00,0.00,1000000.01,BRL', 'payouts', 2, 'net_amount', 'invalid_money'],
            [$orders.str_repeat('a', 101).',2026-01-01,1.00,BRL', 'orders', 2, 'order_id', 'identifier_length'],
            [$payouts.'1,'.str_repeat('p', 101).',2026-01-01,1.00,0.00,1.00,BRL', 'payouts', 2, 'payout_id', 'identifier_length'],
            [$orders."\"a\nb\",2026-01-01,1.00,BRL", 'orders', 2, 'order_id', 'invalid_control'],
            [$orders."1,\"2026-\n01-01\",1.00,BRL", 'orders', 2, 'ordered_at', 'invalid_control'],
            [$orders."1,2026-01-01,\"1.\n00\",BRL", 'orders', 2, 'gross_amount', 'invalid_control'],
            [$orders."1,2026-01-01,1.00,\"BR\nL\"", 'orders', 2, 'currency', 'invalid_control'],
            [$payouts."1,\"p\nx\",2026-01-01,1.00,0.00,1.00,BRL", 'payouts', 2, 'payout_id', 'invalid_control'],
            [$orders."a\t,2026-01-01,1.00,BRL", 'orders', 2, 'order_id', 'invalid_control'],
            [$orders."a\x7F,2026-01-01,1.00,BRL", 'orders', 2, 'order_id', 'invalid_control'],
            [$orders."a\u{0085},2026-01-01,1.00,BRL", 'orders', 2, 'order_id', 'invalid_control'],
            [$orders."a\u{FEFF},2026-01-01,1.00,BRL", 'orders', 2, 'order_id', 'invalid_control'],
            [$orders."1,2026-01-01,1.00,BRL\n\n", 'orders', 3, 'record', 'blank_record'],
            [$orders.'"a,2026-01-01,1.00,BRL', 'orders', 2, 'record', 'invalid_quotes'],
            [$orders."a\0,2026-01-01,1.00,BRL", 'orders', 2, 'file', 'invalid_control'],
            [$orders."a\xFF,2026-01-01,1.00,BRL", 'orders', 2, 'file', 'invalid_utf8'],
        ];
    }

    public function test_trims_only_ascii_spaces_preserving_case_and_unicode(): void
    {
        $preview = $this->validate(self::ORDERS."\n AbC , 2026-01-01 , 0001.00 , BRL \nabc,2026-01-01,1.00,BRL\n".str_repeat('é', 100).",2026-01-01,1.00,BRL\n\u{00A0}id\u{00A0},2026-01-01,1.00,BRL");
        $this->assertTrue($preview->valid);
        $this->assertSame(['AbC', 'abc', str_repeat('é', 100), "\u{00A0}id\u{00A0}"], array_map(fn ($row): string => $row->orderId, $preview->rows));
    }

    public function test_hash_uses_exact_canonical_json_and_excludes_representation(): void
    {
        $plain = $this->validate(self::ORDERS."\n0001,2026-01-01,1.00,BRL\n");
        $variant = $this->validate("\xEF\xBB\xBF".self::ORDERS."\r\n\" 0001 \",\"2026-01-01\",000001.00, BRL \r\n");
        $canonical = '{"kind":"orders","rows":[{"orderId":"0001","payoutId":null,"businessDate":"2026-01-01","grossCents":100,"feeCents":null,"netCents":null,"currency":"BRL"}]}';
        $this->assertTrue($variant->valid);
        $this->assertSame(hash('sha256', $canonical), $plain->normalizedHash);
        $this->assertSame($plain->normalizedHash, $variant->normalizedHash);
    }

    public function test_hash_preserves_input_order_and_changes_for_business_fields(): void
    {
        $first = '1,2026-01-01,1.00,BRL';
        $second = '2,2026-01-02,2.00,BRL';
        $base = $this->validate(self::ORDERS."\n".$first."\n".$second);
        $this->assertNotSame($base->normalizedHash, $this->validate(self::ORDERS."\n".$second."\n".$first)->normalizedHash);
        foreach (['01,2026-01-01,1.00,BRL', '1,2026-01-02,1.00,BRL', '1,2026-01-01,1.01,BRL'] as $changed) {
            $this->assertNotSame($this->validate(self::ORDERS."\n".$first)->normalizedHash, $this->validate(self::ORDERS."\n".$changed)->normalizedHash);
        }
        $payout = $this->validate(self::PAYOUTS."\n1,p,2026-01-01,1.00,0.00,1.00,BRL", 'payouts');
        $this->assertNotSame($this->validate(self::ORDERS."\n".$first)->normalizedHash, $payout->normalizedHash);
        $this->assertNotSame($payout->normalizedHash, $this->validate(self::PAYOUTS."\n1,q,2026-01-01,1.00,0.00,1.00,BRL", 'payouts')->normalizedHash);
        $this->assertNotSame($payout->normalizedHash, $this->validate(self::PAYOUTS."\n1,p,2026-01-01,1.00,0.01,0.99,BRL", 'payouts')->normalizedHash);
    }

    public function test_accepts_one_hundred_records_with_exact_64_bit_total_and_rejects_101(): void
    {
        $rows = array_map(fn (int $id): string => "$id,2026-01-01,1000000.00,BRL", range(1, 100));
        $preview = $this->validate(self::ORDERS."\n".implode("\n", $rows));
        $this->assertTrue($preview->valid);
        $this->assertSame(100, $preview->rowCount);
        $this->assertCount(100, $preview->rows);
        $this->assertSame(10000000000, $preview->grossTotalCents);
        $this->assertSame(Money::format(10000000000), $preview->grossTotal);
        $invalid = $this->validate(self::ORDERS."\n".implode("\n", $rows)."\n101,2026-01-01,1.00,BRL");
        $this->assertFalse($invalid->valid);
        $this->assertSame('record_count', $invalid->errors[0]['code']);
        $this->assertLessThanOrEqual(100, count($invalid->rows));
    }

    public function test_accepts_exact_byte_limit_and_rejects_one_byte_more(): void
    {
        $csv = self::ORDERS."\n1,2026-01-01,1.00,BRL";
        $csv .= str_repeat(' ', 1048576 - strlen($csv));
        $this->assertTrue($this->validate($csv)->valid);
        $invalid = $this->validate($csv.' ');
        $this->assertFalse($invalid->valid);
        $this->assertSame('file_too_large', $invalid->errors[0]['code']);
    }

    public function test_caps_errors_but_continues_validating_without_hashing_partial_rows(): void
    {
        $csv = self::PAYOUTS."\n".implode("\n", array_fill(0, 99, ',,,,,,'))."\nlast,p,2026-01-01,1.00,0.00,1.00,BRL";
        $preview = $this->validate($csv, 'payouts');
        $this->assertFalse($preview->valid);
        $this->assertNull($preview->normalizedHash);
        $this->assertSame(100, $preview->rowCount);
        $this->assertCount(100, $preview->errors);
        $this->assertCount(1, $preview->rows);
        $this->assertSame(101, $preview->rows[0]->line);
        $this->assertSame('1.00', $preview->grossTotal);
    }

    public function test_error_after_multiline_uses_physical_record_start(): void
    {
        $preview = $this->validate(self::ORDERS."\n\"a\r\nb\",2026-01-01,1.00,BRL\r\nc,2026-02-30,1.00,BRL");
        $this->assertSame([2, 4], array_column($preview->errors, 'line'));
    }

    public function test_business_period_is_inclusive_by_calendar_date_not_time_or_timezone(): void
    {
        $preview = (new CsvImportValidator)->validate(
            SourceKind::Orders, self::ORDERS."\n1,2024-02-29,1.00,BRL",
            new DateTimeImmutable('2024-02-29T23:59:59-03:00'), new DateTimeImmutable('2024-02-29T00:00:00+09:00'),
        );
        $this->assertTrue($preview->valid);
    }

    public function test_inverted_period_is_explicit_error(): void
    {
        $preview = (new CsvImportValidator)->validate(
            SourceKind::Orders, self::ORDERS."\n1,2026-01-01,1.00,BRL",
            new DateTimeImmutable('2026-12-31'), new DateTimeImmutable('2026-01-01'),
        );
        $this->assertFalse($preview->valid);
        $this->assertSame('invalid_period', $preview->errors[0]['code']);
    }

    #[DataProvider('controlledFields')]
    public function test_rejects_controls_and_unicode_line_separators_in_every_field(int $column, string $control): void
    {
        $values = ['1', 'p', '2026-01-01', '1.00', '0.00', '1.00', 'BRL'];
        $values[$column] = '"'.$values[$column].$control.'"';
        $preview = $this->validate(self::PAYOUTS."\n".implode(',', $values), 'payouts');
        $this->assertFalse($preview->valid);
        $this->assertSame('invalid_control', $preview->errors[0]['code']);
        $this->assertSame(explode(',', self::PAYOUTS)[$column], $preview->errors[0]['field']);
    }

    public static function controlledFields(): array
    {
        $cases = [];
        foreach (range(0, 6) as $column) {
            foreach (["\t", "\r\n", "\u{2028}", "\u{2029}"] as $control) {
                $cases[] = [$column, $control];
            }
        }

        return $cases;
    }

    public function test_fee_limit_is_validated_even_when_net_is_invalid(): void
    {
        $preview = $this->validate(self::PAYOUTS."\n1,p,2026-01-01,1.00,2.00,invalid,BRL", 'payouts');
        $this->assertFalse($preview->valid);
        $this->assertContains('fee_exceeds_gross', array_column($preview->errors, 'code'));
        $this->assertContains('invalid_money', array_column($preview->errors, 'code'));
    }

    public function test_quoted_headers_and_literal_backslashes_follow_rfc_escaping(): void
    {
        $preview = $this->validate('"order_id","ordered_at","gross_amount","currency"'."\n".'"a\path,""quoted""",2026-01-01,1.00,BRL');
        $this->assertTrue($preview->valid);
        $this->assertSame('a\path,"quoted"', $preview->rows[0]->orderId);
    }

    public function test_row_line_is_never_part_of_canonical_json(): void
    {
        $first = new ImportRow(2, 'á/0001', null, '2026-01-01', 123, null, null);
        $second = new ImportRow(99, 'á/0001', null, '2026-01-01', 123, null, null);
        $this->assertSame($first->canonical(), $second->canonical());
        $canonical = '{"kind":"orders","rows":[{"orderId":"á/0001","payoutId":null,"businessDate":"2026-01-01","grossCents":123,"feeCents":null,"netCents":null,"currency":"BRL"}]}';
        $preview = $this->validate(self::ORDERS."\ná/0001,2026-01-01,1.23,BRL");
        $this->assertSame(hash('sha256', $canonical), $preview->normalizedHash);
    }

    public function test_formula_like_identifiers_remain_literal_text(): void
    {
        $ids = ['=1+1', '+1', '-1', '@id', '0', '00'];
        $rows = array_map(fn (string $id): string => "$id,2026-01-01,1.00,BRL", $ids);
        $preview = $this->validate(self::ORDERS."\n".implode("\n", $rows));
        $this->assertTrue($preview->valid);
        $this->assertSame($ids, array_map(fn (ImportRow $row): string => $row->orderId, $preview->rows));
    }

    public function test_byte_limit_includes_bom(): void
    {
        $csv = "\xEF\xBB\xBF".self::ORDERS."\n1,2026-01-01,1.00,BRL";
        $csv .= str_repeat(' ', 1048576 - strlen($csv));
        $this->assertTrue($this->validate($csv)->valid);
        $this->assertFalse($this->validate($csv.' ')->valid);
    }

    public function test_reports_all_width_errors_without_normalizing_any_record(): void
    {
        $preview = $this->validate(self::ORDERS."\n1,2026-01-01,1.00,BRL,extra\n2,2026-01-01,1.00");
        $this->assertFalse($preview->valid);
        $this->assertSame(2, $preview->rowCount);
        $this->assertSame([2, 3], array_column($preview->errors, 'line'));
        $this->assertSame(['field_count', 'field_count'], array_column($preview->errors, 'code'));
        $this->assertSame([], $preview->rows);
    }
}
