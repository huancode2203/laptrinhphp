<?php

namespace App;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\HubNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperationalService
{
    public function claim(User $user, Booking $booking, array $data): object
    {
        abort_unless($booking->user_id === $user->id && $user->role === 'customer', 403);
        app(FeatureService::class)->media($data['media_ids'] ?? [], $user);

        return DB::transaction(function () use ($user, $booking, $data): object {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($old = DB::table('bank_transfer_claims')->where('request_key', $data['request_key'])->first()) {
                abort_unless($old->user_id === $user->id && $old->booking_id === $booking->id && $old->amount === (int) $data['amount'] && $old->bank_reference === $data['bank_reference'], 409);

                return $old;
            }
            abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Lịch này không còn nhận thanh toán. Liên hệ chi nhánh để đối chiếu.');
            abort_if(DB::table('bank_transfer_claims')->where('user_id', $user->id)->where('bank_reference', $data['bank_reference'])->exists(), 409, 'Mã chuyển khoản này đã gửi. Theo dõi kết quả trong chi tiết lịch.');
            $id = DB::table('bank_transfer_claims')->insertGetId(['request_key' => $data['request_key'], 'user_id' => $user->id, 'booking_id' => $booking->id, 'amount' => $data['amount'], 'bank_reference' => $data['bank_reference'], 'media_ids' => json_encode($data['media_ids'] ?? []), 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('bank_transfer_claims')->find($id);
        }, 5);
    }

    public function reviewClaim(User $actor, int $id, array $data): object
    {
        return DB::transaction(function () use ($actor, $id, $data): object {
            $claim = DB::table('bank_transfer_claims')->where('id', $id)->lockForUpdate()->first();
            abort_unless($claim, 404);
            $booking = Booking::whereKey($claim->booking_id)->lockForUpdate()->firstOrFail();
            app(BookingService::class)->assertManage($actor, $booking->court->branch_id, 'payments.write');
            if ($claim->status !== 'submitted') {
                abort_unless($claim->status === $data['status'], 409, 'Yêu cầu này đã được xử lý.');

                return $claim;
            }
            if ($data['status'] === 'approved') {
                abort_if(app(PayosService::class)->pendingFor([$booking->id])->exists(), 409, 'Lịch có liên kết PayOS chưa kết thúc. Kiểm tra PayOS trước khi xác nhận chuyển khoản riêng.');
                app(BookingService::class)->recordPayment($actor, $booking, ['request_key' => $claim->request_key, 'amount' => $claim->amount, 'method' => 'bank', 'notes' => 'Đối chiếu sao kê '.$claim->bank_reference.' · '.$data['notes']]);
            }
            DB::table('bank_transfer_claims')->where('id', $id)->update(['status' => $data['status'], 'notes' => $data['notes'], 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            $booking->user->notify(new HubNotification(['type' => 'payment.claim_reviewed', 'title' => $data['status'] === 'approved' ? 'Đã xác nhận chuyển khoản' : 'Chưa xác nhận được chuyển khoản', 'body' => $booking->reference.': '.$data['notes']]));
            app(BookingService::class)->audit($actor, 'bank_claim.reviewed', $id, ['status' => $data['status'], 'notes' => $data['notes'], 'booking_id' => $booking->id], 'bank_claim');

            return DB::table('bank_transfer_claims')->find($id);
        }, 5);
    }

    public function reconcile(User $actor, int $id, string $notes): object
    {
        $row = DB::table('payment_reconciliations')->find($id);
        abort_unless($row, 404);
        $attempt = PaymentAttempt::findOrFail($row->payment_attempt_id);
        if ($attempt->booking_id) {
            app(BookingService::class)->assertManage($actor, $attempt->booking->court->branch_id, 'payments.write');
        } else {
            abort_unless($actor->role === 'admin', 403);
        }
        if ($row->status === 'resolved') {
            return $row;
        }
        try {
            app(PayosService::class)->sync($attempt, true);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }
        }

        return DB::transaction(function () use ($actor, $row, $attempt, $notes): object {
            $current = DB::table('payment_reconciliations')->where('id', $row->id)->lockForUpdate()->first();
            $attempt->refresh();
            if ($current->status !== 'resolved') {
                abort_unless($attempt->review_reason && $attempt->received_amount > 0 && in_array($current->provider_status, ['CANCELLED', 'EXPIRED'], true), 422, 'Chỉ chốt tiền chuyển thiếu khi PayOS xác nhận liên kết đã kết thúc.');
                $extra = json_decode(base64_decode($attempt->extra_data), true);
                abort_unless(! empty($extra['payment_link_id']), 422);
                app(PaymentSettlement::class)->receive($attempt, 0, 'PAYOS:'.$extra['payment_link_id'], $attempt->received_amount, true);
            }
            DB::table('payment_reconciliations')->where('id', $row->id)->update(['resolved_by' => $actor->id, 'notes' => $notes, 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'payment.reconciled', $attempt->id, ['amount' => $attempt->received_amount, 'notes' => $notes], 'payment_attempt');

            return DB::table('payment_reconciliations')->find($row->id);
        }, 5);
    }

    public function openShift(User $actor, array $data): object
    {
        app(BookingService::class)->assertManage($actor, (int) $data['branch_id'], 'payments.write');

        return DB::transaction(function () use ($actor, $data): object {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if ($old = DB::table('cash_shifts')->where('request_key', $data['request_key'])->first()) {
                abort_unless($old->user_id === $actor->id && $old->branch_id === (int) $data['branch_id'] && $old->opening_cash === (int) $data['opening_cash'], 409);

                return $old;
            }
            abort_if(DB::table('cash_shifts')->where('user_id', $actor->id)->where('status', 'open')->exists(), 409, 'Bạn còn ca chưa chốt. Hãy chốt ca trước khi mở ca mới.');
            $counters = [];
            foreach (['payments', 'commerce_payments', 'wallet_entries', 'expenses', 'purchase_orders'] as $table) {
                $counters[$table] = (int) DB::table($table)->max('id');
            }
            $id = DB::table('cash_shifts')->insertGetId(['request_key' => $data['request_key'], 'branch_id' => $data['branch_id'], 'user_id' => $actor->id, 'opening_cash' => $data['opening_cash'], 'opening_counters' => json_encode($counters), 'status' => 'open', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('cash_shifts')->find($id);
        }, 5);
    }

    public function closeShift(User $actor, int $id, array $data): object
    {
        return DB::transaction(function () use ($actor, $id, $data): object {
            $shift = DB::table('cash_shifts')->where('id', $id)->lockForUpdate()->first();
            abort_unless($shift, 404);
            app(BookingService::class)->assertManage($actor, $shift->branch_id, 'payments.write');
            abort_unless($actor->id === $shift->user_id || in_array($actor->role, ['admin', 'manager'], true), 403);
            if ($shift->status === 'closed') {
                abort_unless($shift->counted_cash === (int) $data['counted_cash'], 409, 'Ca đã chốt không thể sửa số tiền.');

                return $shift;
            }
            $expected = $this->shiftCash($shift);
            $difference = $data['counted_cash'] - $expected;
            abort_if($difference !== 0 && empty($data['notes']), 422, 'Nhập lý do chênh lệch tiền mặt trước khi chốt ca.');
            DB::table('cash_shifts')->where('id', $id)->update(['expected_cash' => $expected, 'counted_cash' => $data['counted_cash'], 'difference' => $difference, 'notes' => $data['notes'] ?? null, 'closed_at' => now(), 'closed_by' => $actor->id, 'status' => 'closed', 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'cash_shift.closed', $id, ['expected_cash' => $expected, 'counted_cash' => $data['counted_cash'], 'difference' => $difference, 'notes' => $data['notes'] ?? ''], 'cash_shift');

            return DB::table('cash_shifts')->find($id);
        }, 5);
    }

    public function shiftCash(object $shift): int
    {
        $end = $shift->closed_at ?? now();
        $counters = json_decode($shift->opening_counters ?? '{}', true);
        $payments = DB::table('payments')->where('payments.id', '>', $counters['payments'] ?? 0)->join('bookings', 'bookings.id', '=', 'payments.booking_id')->join('courts', 'courts.id', '=', 'bookings.court_id')->where('courts.branch_id', $shift->branch_id)->where('payments.received_by', $shift->user_id)->where('payments.method', 'cash')->whereBetween('payments.created_at', [$shift->opened_at, $end])->sum(DB::raw("CASE WHEN payments.kind = 'refund' THEN -payments.amount ELSE payments.amount END"));
        $commerce = DB::table('commerce_payments')->where('commerce_payments.id', '>', $counters['commerce_payments'] ?? 0)->where('branch_id', $shift->branch_id)->where('actor_id', $shift->user_id)->where('method', 'cash')->whereBetween('created_at', [$shift->opened_at, $end])->sum(DB::raw("CASE WHEN kind = 'refund' THEN -amount ELSE amount END"));
        $topups = DB::table('wallet_entries')->where('wallet_entries.id', '>', $counters['wallet_entries'] ?? 0)->where('branch_id', $shift->branch_id)->where('actor_id', $shift->user_id)->where('kind', 'topup')->where('description', 'like', '% · cash')->whereBetween('created_at', [$shift->opened_at, $end])->sum('amount');
        $out = DB::table('expenses')->where('expenses.id', '>', $counters['expenses'] ?? 0)->where('branch_id', $shift->branch_id)->where('created_by', $shift->user_id)->where('method', 'cash')->whereBetween('created_at', [$shift->opened_at, $end])->sum('amount');
        $purchases = DB::table('purchase_orders')->where('purchase_orders.id', '>', $counters['purchase_orders'] ?? 0)->where('branch_id', $shift->branch_id)->where('created_by', $shift->user_id)->where('method', 'cash')->whereBetween('received_at', [$shift->opened_at, $end])->sum('paid_amount');

        return (int) ($shift->opening_cash + $payments + $commerce + $topups - $out - $purchases);
    }

    public function purchase(User $actor, array $data): object
    {
        app(BookingService::class)->assertManage($actor, (int) $data['branch_id'], 'catalog.write');

        return DB::transaction(function () use ($actor, $data): object {
            $supplier = DB::table('suppliers')->where('id', $data['supplier_id'])->lockForUpdate()->first();
            abort_unless($supplier && $supplier->branch_id === (int) $data['branch_id'] && $supplier->active, 422, 'Nhà cung cấp không hoạt động hoặc thuộc chi nhánh khác.');
            if ($old = DB::table('purchase_orders')->where('request_key', $data['request_key'])->first()) {
                $actual = DB::table('purchase_order_items')->where('purchase_order_id', $old->id)->orderBy('product_id')->get(['product_id', 'quantity', 'unit_cost'])->map(fn ($r) => (array) $r)->all();
                $wanted = collect($data['items'])->map(fn ($r) => array_map('intval', $r))->sortBy('product_id')->values()->all();
                abort_unless($old->created_by === $actor->id && $old->branch_id === (int) $data['branch_id'] && $old->supplier_id === (int) $data['supplier_id'] && $actual === $wanted && $old->method === $data['method'], 409);

                return $old;
            }
            $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                abort_unless($product && $product->branch_id === (int) $data['branch_id'] && $product->stock + $item['quantity'] <= 1000000, 422, 'Sản phẩm sai chi nhánh hoặc số lượng nhập vượt giới hạn kho.');
                $total += $item['quantity'] * $item['unit_cost'];
            }
            abort_if($total > 2147483647, 422, 'Giá trị phiếu nhập vượt giới hạn.');
            $id = DB::table('purchase_orders')->insertGetId(['request_key' => $data['request_key'], 'reference' => 'NH-'.Str::upper(Str::random(10)), 'branch_id' => $data['branch_id'], 'supplier_id' => $data['supplier_id'], 'created_by' => $actor->id, 'status' => 'received', 'total_amount' => $total, 'paid_amount' => $total, 'method' => $data['method'], 'notes' => $data['notes'] ?? null, 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $cost = (int) round(($product->stock * $product->unit_cost + $item['quantity'] * $item['unit_cost']) / ($product->stock + $item['quantity']));
                DB::table('purchase_order_items')->insert(['purchase_order_id' => $id, 'product_id' => $product->id, 'name' => $product->name, 'quantity' => $item['quantity'], 'unit_cost' => $item['unit_cost'], 'amount' => $item['quantity'] * $item['unit_cost']]);
                $product->update(['stock' => $product->stock + $item['quantity'], 'unit_cost' => $cost]);
            }
            app(BookingService::class)->audit($actor, 'purchase.received', $id, ['total' => $total, 'branch_id' => $data['branch_id']], 'purchase');

            return DB::table('purchase_orders')->find($id);
        }, 5);
    }

    public function expense(User $actor, array $data): object
    {
        app(BookingService::class)->assertManage($actor, (int) $data['branch_id'], 'reports.read');
        abort_unless(in_array($actor->role, ['admin', 'manager'], true), 403);

        return DB::transaction(function () use ($actor, $data): object {
            if ($old = DB::table('expenses')->where('request_key', $data['request_key'])->first()) {
                abort_unless($old->created_by === $actor->id && collect($data)->except('request_key')->every(fn ($v, $k) => (string) $old->{$k} === (string) $v), 409);

                return $old;
            }
            $id = DB::table('expenses')->insertGetId($data + ['created_by' => $actor->id, 'source_type' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'expense.created', $id, ['amount' => $data['amount'], 'description' => $data['description']], 'expense');

            return DB::table('expenses')->find($id);
        }, 5);
    }

    public function payroll(User $actor, array $data): object
    {
        abort_unless($actor->role === 'admin', 403);
        $worker = User::findOrFail($data['user_id']);
        abort_unless($worker->active && in_array($worker->role, ['manager', 'staff', 'cashier'], true) && app(AccessService::class)->manages($worker, (int) $data['branch_id']), 422, 'Nhân sự không thuộc chi nhánh đã chọn.');
        $net = $data['base_amount'] + $data['allowances'] - $data['deductions'];
        abort_if($net < 0 || $net > 2147483647, 422, 'Lương thực nhận không hợp lệ.');

        return DB::transaction(function () use ($actor, $data, $net): object {
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            if ($old = DB::table('payrolls')->where('request_key', $data['request_key'])->first()) {
                abort_unless(collect($data)->every(fn ($v, $k) => (string) $old->{$k} === (string) $v), 409);

                return $old;
            }
            abort_if(DB::table('payrolls')->where('branch_id', $data['branch_id'])->where('user_id', $data['user_id'])->where('month', $data['month'])->exists(), 409, 'Nhân sự đã có bảng lương trong tháng này.');
            $id = DB::table('payrolls')->insertGetId($data + ['net_amount' => $net, 'created_at' => now(), 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'payroll.created', $id, ['amount' => $net, 'user_id' => $data['user_id'], 'month' => $data['month']], 'payroll');

            return DB::table('payrolls')->find($id);
        }, 5);
    }

    public function payPayroll(User $actor, int $id, string $method): object
    {
        abort_unless($actor->role === 'admin', 403);

        return DB::transaction(function () use ($actor, $id, $method): object {
            $row = DB::table('payrolls')->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->status === 'paid') {
                return $row;
            }
            if ($row->net_amount > 0) {
                DB::table('expenses')->insert(['request_key' => (string) Uuid::uuid5($row->request_key, 'payroll'), 'branch_id' => $row->branch_id, 'created_by' => $actor->id, 'category' => 'salary', 'description' => 'Chi lương '.$row->month.' · '.User::findOrFail($row->user_id)->name, 'amount' => $row->net_amount, 'method' => $method, 'incurred_on' => now()->toDateString(), 'source_type' => 'payroll', 'source_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('payrolls')->where('id', $id)->update(['status' => 'paid', 'paid_by' => $actor->id, 'paid_at' => now(), 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'payroll.paid', $id, ['amount' => $row->net_amount, 'method' => $method], 'payroll');

            return DB::table('payrolls')->find($id);
        }, 5);
    }

    public function exchange(User $actor, Sale $sale, array $data, bool $preview = false): object
    {
        return DB::transaction(function () use ($actor, $sale, $data, $preview): object {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            app(BookingService::class)->assertManage($actor, $sale->branch_id, 'pos.write');
            if ($old = DB::table('sale_adjustments')->where('request_key', $data['request_key'])->first()) {
                abort_unless($old->sale_id === $sale->id && $old->created_by === $actor->id && json_decode($old->items, true) == ['returned' => $data['items'], 'replacements' => $data['replacements'], 'method' => $data['method']], 409);

                return $old;
            }
            abort_unless(! $sale->booking_id && in_array($sale->method, ['cash', 'bank'], true), 422, 'Đổi hàng áp dụng cho đơn POS đã thanh toán riêng. Hàng gộp hóa đơn sân chưa thu tiền dùng chức năng gỡ hàng.');
            abort_unless(DB::table('commerce_payments')->where('subject_type', 'sale')->where('subject_id', $sale->id)->where('kind', 'payment')->sum('amount') >= $sale->total_amount - $sale->exchange_credit, 422, 'Đơn hàng chưa được thanh toán đủ.');
            $items = $sale->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $credit = 0;
            foreach ($data['items'] as $line) {
                $item = $items->get($line['id']);
                abort_unless($item && $line['quantity'] <= $item->quantity - $item->returned_quantity, 422, 'Số lượng đổi vượt phần chưa trả của đơn hàng.');
                $credit += $line['quantity'] * $item->unit_price;
            }
            $ids = $items->pluck('product_id')->merge(array_column($data['replacements'], 'product_id'))->unique()->sort()->all();
            $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $returnedStock = [];
            foreach ($data['items'] as $line) {
                $item = $items[$line['id']];
                $product = $products[$item->product_id];
                abort_if($product->stock + $line['quantity'] > 1000000, 422, 'Tồn kho sau trả vượt giới hạn.');
                $returnedStock[$product->id] = ($returnedStock[$product->id] ?? 0) + $line['quantity'];
            }
            $total = 0;
            foreach ($data['replacements'] as $line) {
                $product = $products->get($line['product_id']);
                abort_unless($product && $product->active && $product->branch_id === $sale->branch_id && $product->stock + ($returnedStock[$product->id] ?? 0) >= $line['quantity'], 422, 'Hàng thay thế không đủ tồn kho hoặc không thuộc chi nhánh.');
                $total += $product->price * $line['quantity'];
            }
            abort_if($total < $credit || $total > 2147483647, 422, 'Chỉ đổi sang hàng có giá trị bằng hoặc cao hơn, không phát sinh hoàn tiền.');
            if ($preview) {
                return (object) ['credit' => $credit, 'total' => $total, 'difference' => $total - $credit];
            }
            abort_unless(isset($data['expected_difference']) && (int) $data['expected_difference'] === $total - $credit, 409, 'Giá đổi hàng đã thay đổi. Xem lại khoản chênh lệch trước khi thu.');
            foreach ($data['items'] as $line) {
                $item = $items[$line['id']];
                $products[$item->product_id]->increment('stock', $line['quantity']);
                $item->increment('returned_quantity', $line['quantity']);
            }
            $replacement = Sale::create(['request_key' => (string) Uuid::uuid5($data['request_key'], 'replacement-sale'), 'reference' => 'DOI-'.Str::upper(Str::random(10)), 'branch_id' => $sale->branch_id, 'clerk_id' => $actor->id, 'user_id' => $sale->user_id, 'total_amount' => $total, 'exchange_credit' => $credit, 'method' => $data['method']]);
            foreach ($data['replacements'] as $line) {
                $product = $products[$line['product_id']];
                $product->decrement('stock', $line['quantity']);
                $replacement->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => $line['quantity'], 'unit_price' => $product->price, 'unit_cost' => $product->unit_cost, 'amount' => $product->price * $line['quantity']]);
            }
            $owner = $sale->user_id ? User::findOrFail($sale->user_id) : $actor;
            app(CommerceService::class)->payment($actor, $owner, 'sale', $replacement->id, $total - $credit, $data['method'], $sale->branch_id);
            if ($sale->user_id) {
                app(LoyaltyService::class)->award($owner, $total - $credit, 'sale-'.$replacement->id, 'Chênh lệch đổi hàng '.$replacement->reference);
            }
            $id = DB::table('sale_adjustments')->insertGetId(['request_key' => $data['request_key'], 'sale_id' => $sale->id, 'replacement_sale_id' => $replacement->id, 'created_by' => $actor->id, 'kind' => 'exchange', 'amount' => $credit, 'items' => json_encode(['returned' => $data['items'], 'replacements' => $data['replacements'], 'method' => $data['method']]), 'notes' => $data['notes'], 'created_at' => now(), 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'sale.exchanged', $sale->id, ['amount' => $total - $credit, 'reference' => $replacement->reference, 'notes' => $data['notes']], 'sale');

            return DB::table('sale_adjustments')->find($id);
        }, 5);
    }

    public function returnUnpaid(User $actor, Sale $sale, array $data): object
    {
        return DB::transaction(function () use ($actor, $sale, $data): object {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            app(BookingService::class)->assertManage($actor, $sale->branch_id, 'pos.write');
            if ($old = DB::table('sale_adjustments')->where('request_key', $data['request_key'])->first()) {
                abort_unless($old->sale_id === $sale->id && $old->created_by === $actor->id && json_decode($old->items, true) === $data['items'], 409);

                return $old;
            }
            $booking = $sale->booking_id ? Booking::whereKey($sale->booking_id)->lockForUpdate()->firstOrFail() : null;
            abort_unless($booking && in_array($booking->status, ['confirmed', 'checked_in'], true), 422, 'Chỉ gỡ hàng chưa thanh toán khỏi hóa đơn sân đang mở.');
            abort_if(app(PayosService::class)->pendingFor([$booking->id])->exists(), 409, 'Hãy hoàn tất hoặc hủy liên kết PayOS trước khi thay đổi hóa đơn.');
            $items = $sale->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            foreach ($data['items'] as $line) {
                $item = $items->get($line['id']);
                abort_unless($item && $line['quantity'] <= $item->quantity - $item->returned_quantity, 422, 'Số lượng trả vượt số lượng chưa trả của đơn hàng.');
                $total += $line['quantity'] * $item->unit_price;
            }
            abort_if($total > $booking->total_amount - $booking->paid_amount || $total > $booking->product_amount, 422, 'Phần hàng này đã được thanh toán hoặc cần đối chiếu hóa đơn; không thể gỡ để tạo khoản hoàn tiền.');
            foreach ($data['items'] as $line) {
                $item = $items[$line['id']];
                $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                abort_if($product->stock + $line['quantity'] > 1000000, 422, 'Tồn kho sau khi trả vượt giới hạn.');
                $product->increment('stock', $line['quantity']);
                $item->increment('returned_quantity', $line['quantity']);
            }
            $booking->decrement('product_amount', $total);
            $booking->decrement('total_amount', $total);
            $id = DB::table('sale_adjustments')->insertGetId(['request_key' => $data['request_key'], 'sale_id' => $sale->id, 'created_by' => $actor->id, 'kind' => 'unpaid_return', 'amount' => $total, 'items' => json_encode($data['items']), 'notes' => $data['notes'], 'created_at' => now(), 'updated_at' => now()]);
            app(BookingService::class)->audit($actor, 'sale.unpaid_return', $sale->id, ['amount' => $total, 'notes' => $data['notes']], 'sale');

            return DB::table('sale_adjustments')->find($id);
        }, 5);
    }
}
