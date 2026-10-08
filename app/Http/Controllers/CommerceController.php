<?php

namespace App\Http\Controllers;

use App\BookingService;
use App\CommerceService;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtHold;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\Review;
use App\Models\Sale;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommerceController extends Controller
{
    public function hold(Request $request, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'court_id' => ['required', 'integer', 'exists:courts,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']]);
        [$start,$end] = $engine->window($data['starts_at'], $data['ends_at']);
        $hold = DB::transaction(function () use ($request, $data, $start, $end, $engine): CourtHold {
            $court = Court::query()->lockForUpdate()->findOrFail($data['court_id']);
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($court->status === 'active' && $court->branch->active, 422);
            if ($actor->role !== 'customer') {
                $engine->assertManage($actor, $court->branch_id);
            }
            $existing = CourtHold::where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $actor->id && $existing->court_id === $court->id && $existing->starts_at->eq($start) && $existing->ends_at->eq($end) && ! $existing->released && $existing->expires_at->gt(now()), 422);

                return $existing;
            }
            if (CourtHold::where('user_id', $actor->id)->where('released', false)->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['court_id' => 'Bạn đang giữ một khung giờ khác. Hãy hoàn tất hoặc bỏ giữ chỗ trước.']);
            }
            if (CourtHold::where('user_id', $actor->id)->where('created_at', '>', now()->subMinutes(30))->count() >= 2) {
                throw ValidationException::withMessages(['court_id' => 'Bạn đã dùng hai lượt giữ chỗ trong 30 phút.']);
            }
            $engine->quote($court, $start, $end, [], $actor);
            $engine->assertAvailable($court, $start, $end, null, $actor->id);

            return CourtHold::create(['request_key' => $data['request_key'], 'user_id' => $actor->id, 'court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'expires_at' => now()->addMinutes(5), 'released' => false]);
        }, 5);

        return response()->json(['data' => $hold]);
    }

    public function releaseHold(Request $request, CourtHold $hold): JsonResponse
    {
        abort_unless($hold->user_id === $request->user()->id, 403);
        $hold->update(['released' => true]);

        return response()->json(['message' => 'Đã bỏ giữ chỗ.']);
    }

    public function walletPay(Request $request, Booking $booking, CommerceService $commerce): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid']]);

        return response()->json(['data' => $commerce->payBooking($request->user(), $booking, $data['request_key'])]);
    }

    public function topup(Request $request, User $user, CommerceService $commerce, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'branch_id' => ['required', 'integer', 'exists:branches,id'], 'amount' => ['required', 'integer', 'min:1000', 'max:50000000'], 'method' => ['required', Rule::in(['cash', 'bank'])]]);
        $engine->assertManage($request->user(), (int) $data['branch_id']);
        abort_unless($user->role === 'customer', 422);

        return response()->json(['data' => $commerce->wallet($request->user(), $user, (int) $data['amount'], 'topup', $data['request_key'], 'Nạp tiền tại quầy · '.$data['method'], (int) $data['branch_id'])]);
    }

    public function buyMembership(Request $request, MembershipPlan $plan, CommerceService $commerce): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid']]);
        $data['method'] = 'wallet';

        return response()->json(['data' => $commerce->membership($request->user(), $request->user(), $plan, $data)]);
    }

    public function activateMembership(Request $request, User $user, CommerceService $commerce, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'plan_id' => ['required', 'integer', 'exists:membership_plans,id'], 'branch_id' => ['required', 'integer', 'exists:branches,id'], 'method' => ['required', Rule::in(['cash', 'bank'])]]);
        $engine->assertManage($request->user(), (int) $data['branch_id']);
        abort_unless($user->role === 'customer', 422);

        return response()->json(['data' => $commerce->membership($request->user(), $user, MembershipPlan::findOrFail($data['plan_id']), $data)]);
    }

    public function saveProduct(Request $request, BookingService $engine, ?Product $product = null): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'name' => ['required', 'string', 'max:100'], 'sku' => ['required', 'string', 'max:40', Rule::unique('products')->where('branch_id', $request->integer('branch_id'))->ignore($product?->id)], 'category' => ['required', 'string', 'max:40'], 'icon' => ['sometimes', 'string', 'max:20'], 'price' => ['required', 'integer', 'min:0', 'max:10000000'], 'active' => ['required', 'boolean'], 'stock' => [$product ? 'prohibited' : 'required', 'integer', 'min:0', 'max:1000000']]);
        $engine->assertManage($request->user(), (int) $data['branch_id']);
        if ($product) {
            $engine->assertManage($request->user(), $product->branch_id);
            abort_unless($product->branch_id === (int) $data['branch_id'], 422);
        }
        $product ??= new Product;
        $product->fill($data)->save();
        $engine->audit($request->user(), 'product.saved', $product->id, ['name' => $product->name, 'price' => $product->price], 'product');

        return response()->json(['data' => $product]);
    }

    public function stock(Request $request, Product $product, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'delta' => ['required', 'integer', 'between:-1000000,1000000', 'not_in:0'], 'reason' => ['required', 'string', 'max:255']]);
        $product = DB::transaction(function () use ($request, $product, $data, $engine): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $engine->assertManage($request->user(), $product->branch_id);
            $existing = DB::table('inventory_adjustments')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->product_id === $product->id && $existing->delta === (int) $data['delta'], 422);

                return $product;
            }
            abort_if($product->stock + $data['delta'] < 0 || $product->stock + $data['delta'] > 1000000, 422, 'Tồn kho sau điều chỉnh không hợp lệ.');
            $product->increment('stock', $data['delta']);
            DB::table('inventory_adjustments')->insert(['request_key' => $data['request_key'], 'product_id' => $product->id, 'user_id' => $request->user()->id, 'delta' => $data['delta'], 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $engine->audit($request->user(), 'product.stock_adjusted', $product->id, $data, 'product');

            return $product->refresh();
        }, 5);

        return response()->json(['data' => $product]);
    }

    public function sale(Request $request, CommerceService $commerce): JsonResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'branch_id' => ['required', 'integer', 'exists:branches,id'], 'user_id' => ['nullable', 'integer', 'exists:users,id'], 'method' => ['required', Rule::in(['cash', 'bank', 'wallet'])], 'items' => ['required', 'array', 'min:1', 'max:20'], 'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100']]);

        return response()->json(['data' => $commerce->sale($request->user(), $data)], 201);
    }

    public function saleReceipt(Request $request, Sale $sale, BookingService $engine): JsonResponse
    {
        $engine->assertManage($request->user(), $sale->branch_id);

        return response()->json(['data' => $sale->load(['items', 'branch'])]);
    }

    public function savePlan(Request $request, ?MembershipPlan $plan = null): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50', Rule::unique('membership_plans')->ignore($plan?->id)], 'price' => ['required', 'integer', 'min:0', 'max:10000000'], 'discount_percent' => ['required', 'integer', 'between:0,50'], 'duration_days' => ['required', 'integer', 'between:1,365'], 'active' => ['required', 'boolean']]);
        $plan ??= new MembershipPlan;
        $plan->fill($data)->save();
        app(BookingService::class)->audit($request->user(), 'membership_plan.saved', $plan->id, $data, 'membership_plan');

        return response()->json(['data' => $plan]);
    }

    public function saveCoupon(Request $request, ?Coupon $coupon = null): JsonResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate(['code' => ['required', 'alpha_dash', 'max:30', Rule::unique('coupons')->ignore($coupon?->id)], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'kind' => ['required', Rule::in(['percent', 'fixed'])], 'value' => ['required', 'integer', 'min:1', $request->input('kind') === 'percent' ? 'max:100' : 'max:10000000'], 'minimum_amount' => ['required', 'integer', 'min:0', 'max:10000000'], 'max_discount' => ['nullable', 'integer', 'min:1', 'max:10000000'], 'usage_limit' => ['required', 'integer', 'between:1,1000000'], 'starts_at' => ['required', 'date'], 'expires_at' => ['required', 'date', 'after:starts_at'], 'active' => ['required', 'boolean']]);
        $data['code'] = strtoupper($data['code']);
        $coupon ??= new Coupon;
        $coupon->fill($data)->save();
        app(BookingService::class)->audit($request->user(), 'coupon.saved', $coupon->id, $data, 'coupon');

        return response()->json(['data' => $coupon]);
    }

    public function review(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id && $booking->status === 'completed', 403);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'content' => ['required', 'string', 'max:2000']]);
        $review = Review::updateOrCreate(['booking_id' => $booking->id], $data + ['user_id' => $request->user()->id, 'branch_id' => $booking->court->branch_id]);

        return response()->json(['data' => $review]);
    }

    public function waitlist(Request $request, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['court_id' => ['required', 'integer', 'exists:courts,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']]);
        [$start,$end] = $engine->window($data['starts_at'], $data['ends_at']);
        $court = Court::findOrFail($data['court_id']);
        abort_unless($court->status === 'active' && $court->branch->active, 422);
        $entry = WaitlistEntry::updateOrCreate(['court_id' => $court->id, 'user_id' => $request->user()->id, 'starts_at' => $start, 'ends_at' => $end], ['status' => 'waiting']);

        return response()->json(['data' => $entry], 201);
    }

    public function cancelWaitlist(Request $request, WaitlistEntry $entry): JsonResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);
        $entry->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Đã rời danh sách chờ.']);
    }

    public function checkinToken(Request $request, Booking $booking, BookingService $engine): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            $engine->assertManage($request->user(), $booking->court->branch_id);
        }

        return response()->json(['data' => ['token' => Crypt::encryptString(json_encode(['booking_id' => $booking->id, 'expires_at' => $booking->ends_at->addHour()->timestamp], JSON_THROW_ON_ERROR))]]);
    }

    public function checkin(Request $request, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:2000']]);
        try {
            $payload = json_decode(Crypt::decryptString($data['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['token' => 'Mã QR không hợp lệ.']);
        }
        abort_unless(isset($payload['booking_id'],$payload['expires_at']) && $payload['expires_at'] >= now()->timestamp, 422, 'Mã QR đã hết hạn.');
        $booking = Booking::findOrFail($payload['booking_id']);

        return response()->json(['data' => new BookingResource($engine->changeStatus($request->user(), $booking, 'checked_in'))]);
    }
}
