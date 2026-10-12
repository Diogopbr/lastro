<?php

namespace App\Domain\Reconciliation;

final class ReconciliationRules
{
    /**
     * @param  list<object>  $orders
     * @param  list<object>  $payouts
     * @return list<array{order_id:string,classification:string,order_row_id:?string,payout_row_id:?string,difference_cents:?int,review_status:?string}>
     */
    public function match(array $orders, array $payouts): array
    {
        $payoutById = [];
        foreach ($payouts as $payout) {
            $payoutById['id:'.$payout->order_id] = $payout;
        }
        $results = [];
        $seen = [];
        foreach ($orders as $order) {
            $key = 'id:'.$order->order_id;
            $payout = $payoutById[$key] ?? null;
            $results[] = $this->result($order, $payout);
            $seen[$key] = true;
        }
        foreach ($payouts as $payout) {
            if (! isset($seen['id:'.$payout->order_id])) {
                $results[] = $this->result(null, $payout);
            }
        }

        return $results;
    }

    private function result(?object $order, ?object $payout): array
    {
        $difference = $order === null || $payout === null ? null : (int) $payout->gross_cents - (int) $order->gross_cents;
        $classification = match (true) {
            $order === null => 'missingOrder',
            $payout === null => 'missingPayout',
            $difference === 0 => 'matched',
            default => 'amountMismatch',
        };

        return [
            'order_id' => $order?->order_id ?? $payout->order_id,
            'classification' => $classification,
            'order_row_id' => $order?->id,
            'payout_row_id' => $payout?->id,
            'difference_cents' => $difference,
            'review_status' => $classification === 'matched' ? null : 'pending',
        ];
    }
}
