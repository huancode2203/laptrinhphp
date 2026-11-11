<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\Models\Booking;
use App\Models\Sale;
use App\Models\User;
use App\OperationalService;
use App\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OperationsController extends Controller
{
    public function index(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id', 'page' => 'sometimes|integer|between:1,100000', 'search' => 'nullable|string|max:100', 'state' => 'nullable|in:submitted,approved,rejected,pending,resolved,open,closed,received,draft,paid', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $this->access($request, $kind);
        $table = match ($kind) {
            'claims' => 'bank_transfer_claims', 'reconciliations' => 'payment_reconciliations',
            'shifts' => 'cash_shifts', 'suppliers' => 'suppliers', 'purchases' => 'purchase_orders',
            'expenses' => 'expenses', 'payroll' => 'payrolls', 'sales' => 'sales', default => abort(404),
        };
        $branchColumn = $table.'.branch_id';
        $query = DB::table($table);
        if ($kind === 'claims') {
            $query->join('bookings', 'bookings.id', '=', 'bank_transfer_claims.booking_id')->join('courts', 'courts.id', '=', 'bookings.court_id')->join('users', 'users.id', '=', 'bank_transfer_claims.user_id')->select('bank_transfer_claims.*', 'bookings.reference', 'users.name as customer_name');
            $branchColumn = 'courts.branch_id';
        } elseif ($kind === 'reconciliations') {
            $query->join('payment_attempts', 'payment_attempts.id', '=', 'payment_reconciliations.payment_attempt_id')->leftJoin('bookings', 'bookings.id', '=', 'payment_attempts.booking_id')->leftJoin('courts', 'courts.id', '=', 'bookings.court_id')->select('payment_reconciliations.*', 'payment_attempts.order_id as reference', 'payment_attempts.settled_amount');
            $branchColumn = 'courts.branch_id';
        }
        if (in_array($kind, ['payroll', 'shifts'], true)) {
            $query->join('users', 'users.id', '=', $table.'.user_id')->select($table.'.*', 'users.name as employee_name');
        }
        if ($kind === 'purchases') {
            $query->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')->select('purchase_orders.*', 'suppliers.name as supplier_name');
        }
        $allowed = app(AccessService::class)->branches($request->user());
        if ($request->filled('branch_id')) {
            abort_unless(in_array((int) $data['branch_id'], $allowed, true), 403);
            if ($kind === 'reconciliations' && $request->user()->role === 'admin') {
                $query->where(fn ($q) => $q->where($branchColumn, $data['branch_id'])->orWhereNull('payment_attempts.booking_id'));
            } else {
                $query->where($branchColumn, $data['branch_id']);
            }
        } elseif ($request->user()->role !== 'admin') {
            $query->whereIn($branchColumn, $allowed);
        }
        if ($request->filled('state') && ! in_array($kind, ['expenses', 'sales', 'suppliers'], true)) {
            $query->where($table.'.status', $data['state']);
        }
        if ($request->filled('from')) {
            $query->where($table.'.created_at', '>=', $data['from'].' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where($table.'.created_at', '<=', $data['to'].' 23:59:59');
        }
        if ($request->filled('search')) {
            $column = match ($kind) {
                'claims' => 'bank_transfer_claims.bank_reference', 'reconciliations' => 'payment_attempts.order_id', 'suppliers' => 'name', 'sales', 'purchases' => 'reference', 'expenses' => 'description', 'payroll' => 'month', default => 'notes'
            };
            $query->where($column, 'like', '%'.addcslashes($data['search'], '%_\\').'%');
        }
        $rows = $query->orderByDesc($table.'.id')->paginate(10);
        if ($kind === 'purchases') {
            $items = DB::table('purchase_order_items')->whereIn('purchase_order_id', $rows->getCollection()->pluck('id'))->get()->groupBy('purchase_order_id');
            $rows->getCollection()->transform(function ($row) use ($items) {
                $row->items = $items->get($row->id, collect());

                return $row;
            });
        }
        if ($kind === 'claims') {
            $rows->getCollection()->transform(function ($row) {
                $row->media_ids = json_decode($row->media_ids ?? '[]', true);

                return $row;
            });
        }
        if ($kind === 'shifts') {
            $rows->getCollection()->transform(function ($row) {
                $row->expected_cash = $row->status === 'open' ? app(OperationalService::class)->shiftCash($row) : $row->expected_cash;

                return $row;
            });
        }

        return response()->json(['data' => $rows]);
    }

    public function choices(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'required|integer|exists:branches,id']);
        abort_unless(app(AccessService::class)->manages($request->user(), (int) $data['branch_id']) && collect(['payments.write', 'pos.write', 'catalog.write'])->contains(fn ($permission) => app(AccessService::class)->allows($request->user(), $permission)), 403);
        $branch = (int) $data['branch_id'];
        $staffIds = DB::table('user_branches')->where('branch_id', $branch)->pluck('user_id');

        return response()->json(['data' => ['suppliers' => DB::table('suppliers')->where('branch_id', $branch)->where('active', true)->orderBy('name')->get(['id', 'name']), 'products' => DB::table('products')->where('branch_id', $branch)->orderBy('name')->get(['id', 'name', 'stock', 'unit_cost', 'price']), 'staff' => User::whereIn('role', ['manager', 'staff', 'cashier'])->where('active', true)->where(fn ($q) => $q->where('branch_id', $branch)->orWhereIn('id', $staffIds))->get(['id', 'name'])]]);
    }

    public function action(Request $request, string $action, OperationalService $service): JsonResponse
    {
        $requestKey = ['request_key' => 'required|uuid'];
        $branch = ['branch_id' => 'required|integer|exists:branches,id'];
        $method = ['method' => 'required|in:cash,bank'];
        $data = match ($action) {
            'open-shift' => $request->validate($requestKey + $branch + ['opening_cash' => 'required|integer|between:0,100000000']),
            'close-shift' => $request->validate(['id' => 'required|integer|min:1', 'counted_cash' => 'required|integer|between:0,100000000', 'notes' => 'nullable|string|max:1000']),
            'supplier' => $request->validate($branch + ['id' => 'nullable|integer|min:1', 'name' => 'required|string|max:150', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:300', 'active' => 'required|boolean']),
            'purchase' => $request->validate($requestKey + $branch + $method + ['supplier_id' => 'required|integer|exists:suppliers,id', 'notes' => 'nullable|string|max:1000', 'items' => 'required|array|list|min:1|max:50', 'items.*.product_id' => 'required|integer|distinct|exists:products,id', 'items.*.quantity' => 'required|integer|between:1,10000', 'items.*.unit_cost' => 'required|integer|between:0,10000000']),
            'expense' => $request->validate($requestKey + $branch + $method + ['amount' => 'required|integer|between:1,100000000', 'category' => 'required|in:rent,electricity,maintenance,salary,other', 'description' => 'required|string|max:300', 'incurred_on' => 'required|date_format:Y-m-d|before_or_equal:today']),
            'payroll' => $request->validate($requestKey + $branch + ['user_id' => 'required|integer|exists:users,id', 'month' => 'required|date_format:Y-m', 'base_amount' => 'required|integer|between:0,100000000', 'allowances' => 'required|integer|between:0,100000000', 'deductions' => 'required|integer|between:0,100000000', 'notes' => 'nullable|string|max:1000']),
            'pay-payroll' => $request->validate($method + ['id' => 'required|integer|min:1']),
            'review-claim' => $request->validate(['id' => 'required|integer|min:1', 'status' => 'required|in:approved,rejected', 'notes' => 'required|string|min:5|max:1000']),
            'reconcile' => $request->validate(['id' => 'required|integer|min:1', 'notes' => 'required|string|min:5|max:1000']),
            'return-unpaid' => $request->validate($requestKey + ['sale_id' => 'required|integer|exists:sales,id', 'items' => 'required|array|list|min:1|max:50', 'items.*.id' => 'required|integer|distinct|exists:sale_items,id', 'items.*.quantity' => 'required|integer|between:1,100', 'notes' => 'required|string|min:5|max:1000']),
            'exchange', 'exchange-quote' => $request->validate($requestKey + $method + ['expected_difference' => ($action === 'exchange' ? 'required' : 'sometimes').'|integer|between:0,2147483647', 'sale_id' => 'required|integer|exists:sales,id', 'items' => 'required|array|list|min:1|max:50', 'items.*.id' => 'required|integer|distinct|exists:sale_items,id', 'items.*.quantity' => 'required|integer|between:1,100', 'replacements' => 'required|array|list|min:1|max:50', 'replacements.*.product_id' => 'required|integer|distinct|exists:products,id', 'replacements.*.quantity' => 'required|integer|between:1,100', 'notes' => 'required|string|min:5|max:1000']),
            default => abort(404),
        };
        $result = match ($action) {
            'open-shift' => $service->openShift($request->user(), $data),
            'close-shift' => $service->closeShift($request->user(), $data['id'], $data),
            'supplier' => $this->supplier($request, $data),
            'purchase' => $service->purchase($request->user(), $data),
            'expense' => $service->expense($request->user(), $data),
            'payroll' => $service->payroll($request->user(), $data),
            'pay-payroll' => $service->payPayroll($request->user(), $data['id'], $data['method']),
            'review-claim' => $service->reviewClaim($request->user(), $data['id'], $data),
            'reconcile' => $service->reconcile($request->user(), $data['id'], $data['notes']),
            'return-unpaid' => $service->returnUnpaid($request->user(), Sale::findOrFail($data['sale_id']), $data),
            'exchange', 'exchange-quote' => $service->exchange($request->user(), Sale::findOrFail($data['sale_id']), $data, $action === 'exchange-quote'),
        };

        return response()->json(['data' => $result]);
    }

    private function supplier(Request $request, array $data): object
    {
        app(BookingService::class)->assertManage($request->user(), (int) $data['branch_id'], 'catalog.write');
        $id = $data['id'] ?? null;
        unset($data['id']);
        if ($id) {
            $old = DB::table('suppliers')->find($id);
            abort_unless($old && $old->branch_id === (int) $data['branch_id'], 403);
            DB::table('suppliers')->where('id', $id)->update($data + ['updated_at' => now()]);
        } else {
            $id = DB::table('suppliers')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        }
        app(BookingService::class)->audit($request->user(), 'supplier.saved', $id, ['name' => $data['name']], 'supplier');

        return DB::table('suppliers')->find($id);
    }

    public function claim(Request $request, Booking $booking, OperationalService $service): JsonResponse
    {
        $data = $request->validate(['request_key' => 'required|uuid', 'amount' => 'required|integer|between:1000,50000000', 'bank_reference' => 'required|string|min:3|max:100', 'media_ids' => 'sometimes|array|list|max:3', 'media_ids.*' => 'required|integer|distinct|exists:media_uploads,id']);

        return response()->json(['data' => $service->claim($request->user(), $booking, $data)], 201);
    }

    public function finance(Request $request): JsonResponse|Response
    {
        abort_unless(in_array($request->user()->role, ['admin', 'manager'], true), 403);
        $data = $request->validate(['branch_id' => 'nullable|integer|exists:branches,id', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'format' => 'nullable|in:pdf']);
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays($data['to']) > 366, 422);
        $branches = app(AccessService::class)->branches($request->user());
        if ($request->filled('branch_id')) {
            abort_unless(in_array($request->integer('branch_id'), $branches, true), 403);
            $branches = [$request->integer('branch_id')];
        }
        $report = app(ReportService::class)->data($data['from'], $data['to'], $branches);
        $expenses = (int) DB::table('expenses')->whereIn('branch_id', $branches)->whereBetween('incurred_on', [$data['from'], $data['to']])->sum('amount');
        $purchases = (int) DB::table('purchase_orders')->whereIn('branch_id', $branches)->whereBetween('received_at', [$data['from'].' 00:00:00', $data['to'].' 23:59:59'])->sum('paid_amount');
        $result = ['collected' => $report['net_revenue'], 'expenses' => $expenses, 'purchases' => $purchases, 'difference' => $report['net_revenue'] - $expenses - $purchases, 'basis' => 'Thu từ sân, hàng và dịch vụ trong kỳ trừ chi vận hành và tiền nhập hàng. Đây là báo cáo thu chi nội bộ, chưa phải lợi nhuận kế toán.'];
        if ($request->input('format') === 'pdf') {
            return response(app(ReportService::class)->pdf('Thu chi nội bộ '.$data['from'].' – '.$data['to'], ['Nội dung', 'VND'], [['Thu từ sân, hàng và dịch vụ', $result['collected']], ['Chi vận hành (gồm lương đã chi)', $expenses], ['Nhập kho đã chi', $purchases], ['Chênh lệch thu chi', $result['difference']], ['Cơ sở báo cáo', $result['basis']]]), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="thu-chi.pdf"']);
        }

        return response()->json(['data' => $result]);
    }

    private function access(Request $request, string $kind): void
    {
        $permission = match ($kind) {
            'sales' => 'pos.read', 'suppliers', 'purchases' => 'catalog.write', 'expenses' => 'reports.read', default => 'payments.write'
        };
        abort_unless(app(AccessService::class)->allows($request->user(), $permission), 403);
        if (in_array($kind, ['expenses'], true)) {
            abort_unless(in_array($request->user()->role, ['admin', 'manager'], true), 403);
        }
        if ($kind === 'payroll') {
            abort_unless($request->user()->role === 'admin', 403);
        }
    }
}
