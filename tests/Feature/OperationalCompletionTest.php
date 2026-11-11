<?php

namespace Tests\Feature;

use App\CommerceService;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OperationalCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Branch $branch, string $role = 'manager'): User
    {
        return User::factory()->create(['role' => $role, 'branch_id' => $branch->id]);
    }

    private function operation(string $action, array $data): TestResponse
    {
        return $this->postJson('/api/v1/manage/operations/'.$action, $data);
    }

    public function test_purchase_is_scoped_atomic_and_idempotent_with_weighted_cost(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor($branch);
        $product = Product::factory()->create(['branch_id' => $branch->id, 'stock' => 10, 'unit_cost' => 5000]);
        $this->actingAs($actor);
        $supplier = $this->operation('supplier', ['branch_id' => $branch->id, 'name' => 'Nhà phân phối An Phát', 'active' => true])->assertOk()->json('data.id');
        $data = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'supplier_id' => $supplier, 'method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 7000]]];
        $this->operation('purchase', $data)->assertOk()->assertJsonPath('data.total_amount', 70000);
        $this->operation('purchase', $data)->assertOk();
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(6000, $product->fresh()->unit_cost);
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->getJson('/api/v1/manage/operations/purchases?branch_id='.$branch->id)->assertOk()->assertJsonPath('data.data.0.supplier_name', 'Nhà phân phối An Phát')->assertJsonPath('data.data.0.items.0.quantity', 10);
        $data['items'][0]['quantity'] = 11;
        $this->operation('purchase', $data)->assertStatus(409);
        $this->actingAs($this->actor(Branch::factory()->create()))->getJson('/api/v1/manage/operations/purchases')->assertOk()->assertJsonPath('data.total', 0);
        $this->operation('purchase', $data)->assertForbidden();
    }

    public function test_cross_branch_purchase_rolls_back_stock_and_money(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->actor($branch));
        $supplier = $this->operation('supplier', ['branch_id' => $branch->id, 'name' => 'An Phát', 'active' => true])->json('data.id');
        $product = Product::factory()->create();
        $this->operation('purchase', ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'supplier_id' => $supplier, 'method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 5000]]])->assertUnprocessable();
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertSame(20, $product->fresh()->stock);
    }

    public function test_cash_shift_counts_cash_only_and_requires_explaining_difference(): void
    {
        $this->travelTo(now()->startOfSecond());
        $branch = Branch::factory()->create();
        $actor = $this->actor($branch, 'cashier');
        $this->actingAs($actor);
        $open = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'opening_cash' => 100000];
        $id = $this->operation('open-shift', $open)->assertOk()->json('data.id');
        $this->operation('open-shift', $open)->assertOk();
        $this->operation('open-shift', array_replace($open, ['request_key' => (string) Str::uuid()]))->assertStatus(409);
        $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 10000]);
        foreach (['cash', 'bank'] as $method) {
            app(CommerceService::class)->sale($actor, ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'method' => $method, 'items' => [['product_id' => $product->id, 'quantity' => 2]]]);
        }
        $this->operation('close-shift', ['id' => $id, 'counted_cash' => 119000])->assertUnprocessable();
        $this->operation('close-shift', ['id' => $id, 'counted_cash' => 119000, 'notes' => 'Thiếu 1.000 đồng khi kiểm kê'])->assertOk()->assertJsonPath('data.expected_cash', 120000)->assertJsonPath('data.difference', -1000);
        $this->operation('close-shift', ['id' => $id, 'counted_cash' => 119000])->assertOk();
        $this->operation('close-shift', ['id' => $id, 'counted_cash' => 120000])->assertStatus(409);
        $next = $this->operation('open-shift', array_replace($open, ['request_key' => (string) Str::uuid(), 'opening_cash' => 119000]))->assertOk()->json('data.id');
        $this->operation('close-shift', ['id' => $next, 'counted_cash' => 119000])->assertOk()->assertJsonPath('data.expected_cash', 119000)->assertJsonPath('data.difference', 0);
    }

    public function test_payroll_is_admin_only_and_paid_exactly_once(): void
    {
        $branch = Branch::factory()->create();
        $worker = $this->actor($branch, 'staff');
        $data = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'user_id' => $worker->id, 'month' => '2026-11', 'base_amount' => 6000000, 'allowances' => 500000, 'deductions' => 100000];
        $this->actingAs($this->actor($branch))->operation('payroll', $data)->assertForbidden();
        $this->actingAs($this->actor($branch, 'admin'));
        $id = $this->operation('payroll', $data)->assertOk()->assertJsonPath('data.net_amount', 6400000)->json('data.id');
        $this->operation('payroll', $data)->assertOk();
        $this->operation('payroll', array_replace($data, ['request_key' => (string) Str::uuid()]))->assertStatus(409);
        $this->operation('pay-payroll', ['id' => $id, 'method' => 'bank'])->assertOk();
        $this->operation('pay-payroll', ['id' => $id, 'method' => 'bank'])->assertOk();
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', ['amount' => 6400000, 'source_type' => 'payroll']);
        $this->getJson('/api/v1/manage/operations/payroll')->assertOk()->assertJsonPath('data.data.0.employee_name', $worker->name);
    }

    public function test_transfer_claim_does_not_mark_paid_until_branch_checks_money(): void
    {
        $booking = Booking::factory()->create();
        $claim = ['request_key' => (string) Str::uuid(), 'amount' => 30000, 'bank_reference' => 'MB261111000123'];
        $this->actingAs($booking->user);
        $id = $this->postJson('/api/v1/bookings/'.$booking->id.'/bank-claim', $claim)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/bookings/'.$booking->id.'/bank-claim', $claim)->assertCreated();
        $this->assertSame(0, $booking->fresh()->paid_amount);
        $this->actingAs($this->actor(Branch::factory()->create()))->operation('review-claim', ['id' => $id, 'status' => 'approved', 'notes' => 'Đã đối chiếu tiền vào'])->assertForbidden();
        $this->actingAs($this->actor($booking->court->branch));
        $this->operation('review-claim', ['id' => $id, 'status' => 'approved', 'notes' => 'Đã đối chiếu tiền vào'])->assertOk();
        $this->operation('review-claim', ['id' => $id, 'status' => 'approved', 'notes' => 'Đã đối chiếu tiền vào'])->assertOk();
        $this->assertSame(30000, $booking->fresh()->paid_amount);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_unpaid_product_return_reduces_only_product_amount_once(): void
    {
        $booking = Booking::factory()->create(['status' => 'checked_in', 'paid_amount' => 100000]);
        $actor = $this->actor($booking->court->branch);
        $product = Product::factory()->create(['branch_id' => $booking->court->branch_id, 'price' => 10000, 'unit_cost' => 6000]);
        $sale = app(CommerceService::class)->sale($actor, ['request_key' => (string) Str::uuid(), 'branch_id' => $product->branch_id, 'booking_id' => $booking->id, 'method' => 'booking', 'items' => [['product_id' => $product->id, 'quantity' => 2]]]);
        $this->assertSame(6000, $sale->items->first()->unit_cost);
        $data = ['request_key' => (string) Str::uuid(), 'sale_id' => $sale->id, 'items' => [['id' => $sale->items->first()->id, 'quantity' => 1]], 'notes' => 'Khách chưa mở chai, trả lại kho'];
        $this->actingAs($actor)->operation('return-unpaid', $data)->assertOk();
        $this->operation('return-unpaid', $data)->assertOk();
        $this->assertSame(110000, $booking->fresh()->total_amount);
        $this->assertSame(10000, $booking->fresh()->product_amount);
        $this->assertSame(19, $product->fresh()->stock);
        $booking->update(['paid_amount' => 110000]);
        $data['request_key'] = (string) Str::uuid();
        $this->operation('return-unpaid', $data)->assertUnprocessable();
        $this->assertSame(19, $product->fresh()->stock);
    }

    public function test_expense_validation_permissions_and_finance_totals(): void
    {
        $branch = Branch::factory()->create();
        $data = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'amount' => 200000, 'method' => 'cash', 'description' => 'Thanh toán tiền điện', 'incurred_on' => now()->toDateString(), 'category' => 'electricity'];
        $this->actingAs($this->actor($branch, 'cashier'))->operation('expense', $data)->assertForbidden();
        $this->actingAs($this->actor($branch));
        $this->operation('expense', array_replace($data, ['amount' => -1]))->assertUnprocessable();
        $this->operation('expense', array_replace($data, ['incurred_on' => ['invalid']]))->assertUnprocessable();
        $this->operation('expense', $data)->assertOk();
        $this->operation('expense', $data)->assertOk();
        $this->assertDatabaseCount('expenses', 1);
        $query = http_build_query(['branch_id' => $branch->id, 'from' => now()->toDateString(), 'to' => now()->toDateString()]);
        $this->getJson('/api/v1/manage/operations/finance?'.$query)->assertOk()->assertJsonPath('data.expenses', 200000)->assertJsonPath('data.difference', -200000);
        $this->get('/api/v1/manage/operations/finance?'.$query.'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_customer_cannot_access_internal_operations_or_other_claims(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->getJson('/api/v1/manage/operations/expenses')->assertForbidden();
        $booking = Booking::factory()->create();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/bank-claim', ['request_key' => (string) Str::uuid(), 'amount' => 20000, 'bank_reference' => 'MB-TEST'])->assertForbidden();
        $this->assertDatabaseCount('bank_transfer_claims', 0);
    }

    public function test_exchange_collects_only_difference_updates_stock_once_and_preserves_report_totals(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor($branch);
        $old = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Nước khoáng', 'price' => 10000]);
        $new = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Nước thể thao', 'price' => 15000]);
        $customer = User::factory()->create();
        $sale = app(CommerceService::class)->sale($actor, ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'user_id' => $customer->id, 'method' => 'cash', 'items' => [['product_id' => $old->id, 'quantity' => 2]]]);
        $data = ['request_key' => (string) Str::uuid(), 'sale_id' => $sale->id, 'items' => [['id' => $sale->items->first()->id, 'quantity' => 1]], 'replacements' => [['product_id' => $new->id, 'quantity' => 1]], 'method' => 'bank', 'notes' => 'Đổi chai chưa mở sang nước thể thao'];
        $this->actingAs($actor)->operation('exchange-quote', $data)->assertOk()->assertJsonPath('data.difference', 5000);
        $this->assertSame(18, $old->fresh()->stock);
        $data['expected_difference'] = 5000;
        $this->operation('exchange', $data)->assertOk();
        $this->operation('exchange', $data)->assertOk();
        $this->assertSame(19, $old->fresh()->stock);
        $this->assertSame(19, $new->fresh()->stock);
        $this->assertSame(25000, (int) DB::table('commerce_payments')->sum('amount'));
        $this->assertSame(2, $customer->fresh()->loyalty_points);
        $report = app(ReportService::class)->data(now()->toDateString(), now()->toDateString(), $branch->id);
        $this->assertSame(25000, $report['net_revenue']);
        $this->assertSame(25000, array_sum(array_column($report['by_product'], 'revenue')));
    }

    public function test_lower_value_exchange_rolls_back_and_cannot_create_refund(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor($branch);
        $expensive = Product::factory()->create(['branch_id' => $branch->id, 'price' => 20000]);
        $cheap = Product::factory()->create(['branch_id' => $branch->id, 'price' => 10000]);
        $sale = app(CommerceService::class)->sale($actor, ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'method' => 'cash', 'items' => [['product_id' => $expensive->id, 'quantity' => 1]]]);
        $this->actingAs($actor)->operation('exchange', ['request_key' => (string) Str::uuid(), 'sale_id' => $sale->id, 'items' => [['id' => $sale->items->first()->id, 'quantity' => 1]], 'replacements' => [['product_id' => $cheap->id, 'quantity' => 1]], 'method' => 'cash', 'expected_difference' => 0, 'notes' => 'Đổi sang hàng giá thấp hơn'])->assertUnprocessable();
        $this->assertSame(19, $expensive->fresh()->stock);
        $this->assertSame(20, $cheap->fresh()->stock);
        $this->assertDatabaseCount('sale_adjustments', 0);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_coupon_reschedule_excludes_own_redemption_and_preserves_services(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(8));
        $coupon = Coupon::factory()->create(['usage_limit' => 1, 'expires_at' => now()->addDays(5)]);
        $booking = Booking::factory()->create(['coupon_id' => $coupon->id, 'coupon_code' => $coupon->code, 'discount_amount' => 10000, 'total_amount' => 90000, 'paid_amount' => 30000]);
        $data = ['court_id' => $booking->court_id, 'starts_at' => $booking->starts_at->addDay()->toIso8601String(), 'ends_at' => $booking->ends_at->addDay()->toIso8601String()];
        $this->actingAs($booking->user)->postJson('/api/v1/bookings/'.$booking->id.'/reschedule-quote', $data)->assertOk();
        $this->patchJson('/api/v1/bookings/'.$booking->id.'/reschedule', $data)->assertOk();
        $this->assertSame($coupon->id, $booking->fresh()->coupon_id);
        $this->assertSame(30000, $booking->fresh()->paid_amount);
        $coupon->update(['active' => false]);
        $data['starts_at'] = $booking->fresh()->starts_at->addDay()->toIso8601String();
        $data['ends_at'] = $booking->fresh()->ends_at->addDay()->toIso8601String();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/reschedule-quote', $data)->assertUnprocessable();
        $this->postJson('/api/v1/bookings/'.$booking->id.'/reschedule-quote', $data + ['remove_coupon' => true])->assertOk();
    }
}
