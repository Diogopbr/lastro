<?php

namespace Tests\Unit;

use App\Domain\Reconciliation\ReconciliationRules;
use PHPUnit\Framework\TestCase;

class ReconciliationRulesTest extends TestCase
{
    public function test_exact_join_classifies_four_cases_and_keeps_missing_money_null(): void
    {
        $orders = [$this->row('0001', 10000), $this->row('0002', 20000), $this->row('0003', 30000)];
        $payouts = [$this->row('0001', 10000), $this->row('0002', 19000), $this->row('0004', 40000)];
        $rows = (new ReconciliationRules)->match($orders, $payouts);
        $this->assertSame(['matched', 'amountMismatch', 'missingPayout', 'missingOrder'], array_column($rows, 'classification'));
        $this->assertSame([0, -1000, null, null], array_column($rows, 'difference_cents'));
        $this->assertSame([null, 'pending', 'pending', 'pending'], array_column($rows, 'review_status'));
        $this->assertNull($rows[2]['payout_row_id']);
        $this->assertNull($rows[3]['order_row_id']);
    }

    public function test_zero_padding_case_and_unicode_are_exact_identifiers(): void
    {
        $rows = (new ReconciliationRules)->match(
            [$this->row('0001', 1), $this->row('Ab', 1), $this->row('é', 1), $this->row('0', 1)],
            [$this->row('1', 1), $this->row('ab', 1), $this->row("e\u{0301}", 1), $this->row('00', 1)],
        );
        $this->assertCount(8, $rows);
        $this->assertSame(array_fill(0, 4, 'missingPayout'), array_column(array_slice($rows, 0, 4), 'classification'));
    }

    public function test_computes_both_difference_signs_and_empty_input(): void
    {
        $this->assertSame([], (new ReconciliationRules)->match([], []));
        $rows = (new ReconciliationRules)->match([$this->row('a', 1)], [$this->row('a', 100000000)]);
        $this->assertSame(99999999, $rows[0]['difference_cents']);
    }

    private function row(string $id, int $gross): object
    {
        return (object) ['id' => 'row-'.$id, 'order_id' => $id, 'gross_cents' => $gross];
    }
}
