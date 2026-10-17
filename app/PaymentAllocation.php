<?php

namespace App;

use App\Models\Booking;

class PaymentAllocation
{
    public function capture(Booking $booking, int $amount, string $kind, ?int $beforeId = null, bool $estimated = false): array
    {
        $booking->load('court.branch', 'services', 'sales.items');
        $items = ['court' => ['key' => 'court', 'kind' => 'court', 'name' => $booking->court->name, 'amount' => max(0, $booking->court_amount - $booking->discount_amount)]];
        foreach ($booking->services as $service) {
            $key = 'service:'.$service->id;
            $items[$key] = ['key' => $key, 'kind' => 'service', 'name' => $service->pivot->name, 'amount' => (int) $service->pivot->amount];
        }
        foreach ($booking->sales as $sale) {
            foreach ($sale->items as $item) {
                $key = 'product:'.$item->id;
                $items[$key] = ['key' => $key, 'kind' => 'product', 'name' => $item->name, 'amount' => (int) $item->amount];
            }
        }
        $items = $this->distribute($items, (int) $booking->total_amount);
        $funded = [];
        foreach ($booking->payments()->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))->orderBy('id')->get() as $payment) {
            foreach ($payment->allocation['items'] ?? [] as $item) {
                $key = $item['key'];
                $funded[$key] ??= [...$item, 'amount' => 0];
                $funded[$key]['amount'] += ($payment->kind === 'refund' ? -1 : 1) * $item['amount'];
            }
        }
        if ($kind === 'refund') {
            $weights = array_map(fn ($item) => [...$item, 'amount' => max(0, $item['amount'])], $funded);
        } else {
            $weights = array_map(fn ($item) => [...$item, 'amount' => max(0, $item['amount'] - ($funded[$item['key']]['amount'] ?? 0))], $items);
        }
        if (array_sum(array_column($weights, 'amount')) <= 0) {
            $weights = $items;
        }

        return ['version' => 1, 'estimated' => $estimated, 'court_id' => $booking->court_id, 'court_name' => $booking->court->name, 'branch_id' => $booking->court->branch_id, 'branch_name' => $booking->court->branch->name, 'items' => array_values($this->distribute($weights, $amount))];
    }

    private function distribute(array $items, int $amount): array
    {
        $total = array_sum(array_column($items, 'amount'));
        if ($total <= 0) {
            return ['court' => ['key' => 'court', 'kind' => 'court', 'name' => 'Tiền sân', 'amount' => $amount]];
        }
        $cumulative = 0;
        $previous = 0;
        foreach ($items as &$item) {
            $cumulative += $item['amount'];
            $target = intdiv($amount * $cumulative, $total);
            $item['amount'] = $target - $previous;
            $previous = $target;
        }
        unset($item);

        return $items;
    }
}
