<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\CommerceService;
use App\EventWaitlistService;
use App\Models\Court;
use App\Models\EventRegistration;
use App\Models\GameEvent;
use App\Models\TournamentMatch;
use App\Notifications\HubNotification;
use App\TournamentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['type' => ['sometimes', Rule::in(['friendly', 'league', 'training', 'workshop'])], 'branch_id' => ['sometimes', 'integer', 'exists:branches,id']]);
        $query = GameEvent::whereIn('status', ['published', 'completed'])->with('branch:id,name,district')->withCount(['registrations as confirmed_count' => fn ($q) => $q->where('status', 'confirmed'), 'registrations as reserved_count' => fn ($q) => $q->where('status', 'confirmed')->orWhere(fn ($p) => $p->where('status', 'pending')->where('created_at', '>', now()->subMinutes(30)))]);
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return response()->json(['data' => $query->orderBy('starts_at')->paginate(30)]);
    }

    public function show(Request $request, GameEvent $event): JsonResponse
    {
        $staff = $request->user() && app(AccessService::class)->manages($request->user(), $event->branch_id);
        abort_unless($event->status !== 'cancelled' || $staff || $event->registrations()->where('user_id', $request->user()?->id ?? 0)->exists(), 404);
        $event->load('branch')->loadCount(['registrations as confirmed_count' => fn ($q) => $q->where('status', 'confirmed')]);
        $data = $event->toArray();
        $data['my_registration'] = $request->user() ? $event->registrations()->where('user_id', $request->user()->id)->first() : null;
        $regs = $event->registrations()->with('user:id,name,skill_level')->where($staff ? fn ($q) => $q : fn ($q) => $q->where('status', 'confirmed'))->get();
        $data['registrations'] = $staff ? $regs->toArray() : $regs->map(fn ($reg) => ['id' => $reg->id, 'user' => $reg->user])->all();
        $data['matches'] = $event->matches()->with(['a:id,name', 'b:id,name'])->orderBy('round')->orderBy('position')->get();
        $data['standings'] = app(TournamentService::class)->standings($event);
        $data['tournament_format'] = app(TournamentService::class)->format($event);
        $data['court_ids'] = DB::table('court_blocks')->where('game_event_id', $event->id)->pluck('court_id');

        return response()->json(['data' => $data]);
    }

    public function save(Request $request, BookingService $engine, ?GameEvent $event = null): JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'title' => ['required', 'string', 'max:150'], 'type' => ['required', Rule::in(['friendly', 'league', 'training', 'workshop'])], 'description' => ['required', 'string', 'max:5000'], 'format' => ['required', 'string', 'max:255'], 'skill_level' => ['required', 'string', 'max:40'], 'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'capacity' => ['required', 'integer', 'between:2,500'], 'fee' => ['required', 'integer', 'between:0,10000000'], 'court_ids' => ['required', 'array', 'list', 'min:1', 'max:30'], 'court_ids.*' => ['required', 'integer', 'distinct', 'exists:courts,id']]);
        $engine->assertManage($request->user(), (int) $data['branch_id']);
        $data['court_ids'] = array_map('intval', $data['court_ids']);
        $event = DB::transaction(function () use ($request, $event, $data, $engine): GameEvent {
            $start = CarbonImmutable::parse($data['starts_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
            $end = CarbonImmutable::parse($data['ends_at'], config('app.timezone'))->setTimezone(config('app.timezone'));
            abort_if(! $start->isSameDay($end), 422, 'Sự kiện phải nằm trong giờ mở cửa và trong cùng ngày.');
            $engine->assertOpen(Court::findOrFail($data['court_ids'][0]), $start, $end, $request->user());
            $oldIds = [];
            if ($event) {
                $event = GameEvent::query()->lockForUpdate()->findOrFail($event->id);
                $engine->assertManage($request->user(), $event->branch_id);
                abort_unless($event->branch_id === (int) $data['branch_id'] && $event->status === 'published', 422);
                $oldIds = DB::table('court_blocks')->where('game_event_id', $event->id)->pluck('court_id')->all();
                if ($event->registrations()->whereIn('status', ['pending', 'confirmed'])->exists()) {
                    $newIds = $data['court_ids'];
                    sort($newIds);
                    sort($oldIds);
                    abort_unless($event->starts_at->eq($start) && $event->ends_at->eq($end) && $oldIds === $newIds && $event->type === $data['type'], 422, 'Sự kiện đã có người đăng ký; không thay đổi lịch, sân hoặc thể thức.');
                }
                abort_if($event->registrations()->where('status', 'confirmed')->count() > $data['capacity'], 422, 'Không giảm số chỗ dưới số người đã xác nhận.');
            } else {
                $event = new GameEvent;
                $event->created_by = $request->user()->id;
            }
            $ids = array_values(array_unique(array_merge($oldIds, $data['court_ids'])));
            sort($ids);
            $courts = Court::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($event->exists) {
                DB::table('court_blocks')->where('game_event_id', $event->id)->delete();
            }
            foreach ($data['court_ids'] as $id) {
                $court = $courts[$id];
                abort_unless($court->branch_id === (int) $data['branch_id'] && $court->status === 'active' && $court->branch->active, 422, 'Sân không phù hợp với chi nhánh.');
                $engine->assertAvailable($court, $start, $end);
            }
            $event->fill(collect($data)->except('court_ids')->all())->save();
            foreach ($data['court_ids'] as $id) {
                DB::table('court_blocks')->insert(['court_id' => $id, 'game_event_id' => $event->id, 'starts_at' => $start, 'ends_at' => $end, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $engine->audit($request->user(), 'event.saved', $event->id, $data, 'event');

            return $event;
        }, 5);

        return response()->json(['data' => $event], $event->wasRecentlyCreated ? 201 : 200);
    }

    public function register(Request $request, GameEvent $event, CommerceService $commerce): JsonResponse
    {
        $data = $request->validate(['method' => ['required', Rule::in(['cash', 'wallet'])]]);
        $reg = DB::transaction(function () use ($request, $event, $commerce, $data): EventRegistration {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->status === 'published' && $event->starts_at->gt(now()) && ! $event->matches()->exists(), 422, 'Sự kiện đã đóng đăng ký.');
            app(EventWaitlistService::class)->advance($event);
            $reg = $event->registrations()->where('user_id', $request->user()->id)->lockForUpdate()->first();
            if ($reg && in_array($reg->status, ['pending', 'confirmed', 'waiting'], true)) {
                if ($reg->status === 'pending' && $data['method'] === 'wallet') {
                    $commerce->wallet($request->user(), $request->user(), -$reg->amount, 'event', (string) Str::uuid(), 'Đăng ký '.$event->title, $event->branch_id, 'event_registration', $reg->id);
                    $commerce->payment($request->user(), $request->user(), 'event_registration', $reg->id, $reg->amount, 'wallet', $event->branch_id);
                    $reg->update(['paid_amount' => $reg->amount, 'method' => 'wallet', 'status' => 'confirmed']);
                }

                return $reg;
            }
            abort_if($reg && $reg->paid_amount > 0, 422, 'Cần xử lý khoản hoàn tiền trước khi đăng ký lại.');
            if ($event->registrations()->whereIn('status', ['pending', 'confirmed'])->count() >= $event->capacity) {
                $reg = EventRegistration::updateOrCreate(['game_event_id' => $event->id, 'user_id' => $request->user()->id], ['amount' => $event->fee, 'paid_amount' => 0, 'method' => 'cash', 'status' => 'waiting', 'created_at' => now()]);
                $request->user()->notify(new HubNotification(['type' => 'event.waitlisted', 'title' => 'Đã vào danh sách chờ', 'body' => $event->title, 'event_id' => $event->id]));

                return $reg;
            }
            $reg = EventRegistration::updateOrCreate(['game_event_id' => $event->id, 'user_id' => $request->user()->id], ['amount' => $event->fee, 'paid_amount' => 0, 'method' => $data['method'], 'status' => $event->fee === 0 ? 'confirmed' : 'pending', 'created_at' => now()]);
            if ($event->fee > 0 && $data['method'] === 'wallet') {
                $commerce->wallet($request->user(), $request->user(), -$event->fee, 'event', (string) Str::uuid(), 'Đăng ký '.$event->title, $event->branch_id, 'event_registration', $reg->id);
                $commerce->payment($request->user(), $request->user(), 'event_registration', $reg->id, $event->fee, 'wallet', $event->branch_id);
                $reg->update(['paid_amount' => $event->fee, 'status' => 'confirmed']);
            }
            $request->user()->notify(new HubNotification(['type' => 'event.registered', 'title' => 'Đã ghi nhận đăng ký sự kiện', 'body' => $event->title.($reg->status === 'pending' ? ' · Thanh toán tại quầy trong 30 phút để xác nhận.' : ''), 'event_id' => $event->id]));

            return $reg;
        }, 5);

        return response()->json(['data' => $reg]);
    }

    public function confirm(Request $request, EventRegistration $registration, BookingService $engine, CommerceService $commerce): JsonResponse
    {
        $data = $request->validate(['method' => ['required', Rule::in(['cash', 'bank'])]]);
        $reg = DB::transaction(function () use ($request, $registration, $engine, $commerce, $data): EventRegistration {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($registration->game_event_id);
            $engine->assertManage($request->user(), $event->branch_id);
            $reg = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if ($reg->status === 'confirmed') {
                return $reg;
            }
            abort_unless($event->status === 'published' && $event->starts_at->gt(now()) && $reg->status === 'pending' && $reg->created_at->gt(now()->subMinutes(30)), 422, 'Đăng ký đã hết hạn hoặc không nhận thanh toán.');
            $commerce->payment($request->user(), $reg->user, 'event_registration', $reg->id, $reg->amount, $data['method'], $event->branch_id);
            $reg->update(['paid_amount' => $reg->amount, 'method' => $data['method'], 'status' => 'confirmed']);
            $reg->user->notify(new HubNotification(['type' => 'event.confirmed', 'title' => 'Đã xác nhận vé sự kiện', 'body' => $event->title, 'event_id' => $event->id]));

            return $reg;
        }, 5);

        return response()->json(['data' => $reg]);
    }

    private function cancelRegistration(Request $request, EventRegistration $reg, CommerceService $commerce): void
    {
        $reg->status = 'cancelled';
        if ($reg->paid_amount > 0 && $reg->method === 'wallet') {
            $commerce->wallet($request->user(), $reg->user, $reg->paid_amount, 'refund', (string) Str::uuid(), 'Hoàn vé '.$reg->event->title, $reg->event->branch_id, 'event_registration', $reg->id);
            $commerce->payment($request->user(), $reg->user, 'event_registration', $reg->id, $reg->paid_amount, 'wallet', $reg->event->branch_id, 'refund');
            $reg->paid_amount = 0;
        }
        $reg->save();
    }

    public function withdraw(Request $request, GameEvent $event, CommerceService $commerce): JsonResponse
    {
        DB::transaction(function () use ($request, $event, $commerce): void {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($event->id);
            $reg = $event->registrations()->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($reg->status === 'cancelled') {
                return;
            }
            abort_unless($event->starts_at->gt(now()) && ! $event->matches()->exists(), 422, 'Sự kiện đã bắt đầu hoặc đã chốt danh sách.');
            abort_if($reg->paid_amount > 0 && $event->starts_at->lt(now()->addHours(4)), 422, 'Vé đã thanh toán cần hủy trước ít nhất 4 tiếng.');
            $this->cancelRegistration($request, $reg, $commerce);
            app(EventWaitlistService::class)->advance($event);
        }, 5);

        return response()->json(['message' => 'Đã hủy đăng ký. Khoản thu tại quầy cần quầy xử lý hoàn tiền.']);
    }

    public function refund(Request $request, EventRegistration $registration, CommerceService $commerce, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['method' => ['required', Rule::in(['cash', 'bank'])]]);
        DB::transaction(function () use ($request, $registration, $commerce, $engine, $data): void {
            $reg = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $engine->assertManage($request->user(), $reg->event->branch_id);
            abort_unless($reg->status === 'cancelled' && $reg->paid_amount > 0, 422);
            $commerce->payment($request->user(), $reg->user, 'event_registration', $reg->id, $reg->paid_amount, $data['method'], $reg->event->branch_id, 'refund');
            $reg->update(['paid_amount' => 0]);
        }, 5);

        return response()->json(['message' => 'Đã ghi nhận hoàn tiền vé tại quầy.']);
    }

    public function status(Request $request, GameEvent $event, BookingService $engine, CommerceService $commerce): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['cancelled', 'completed'])]]);
        DB::transaction(function () use ($request, $event, $engine, $commerce, $data): void {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($event->id);
            $engine->assertManage($request->user(), $event->branch_id);
            if ($event->status === $data['status']) {
                return;
            }
            abort_unless($event->status === 'published', 422);
            if ($data['status'] === 'completed') {
                abort_if(now()->lt($event->ends_at) || $event->matches()->whereNull('winner_id')->exists(), 422, 'Chưa hết giờ hoặc còn trận chưa chốt kết quả.');
            } else {
                foreach ($event->registrations()->whereIn('status', ['pending', 'confirmed', 'waiting', 'expired'])->lockForUpdate()->get() as $reg) {
                    $this->cancelRegistration($request, $reg, $commerce);
                    $reg->user->notify(new HubNotification(['type' => 'event.cancelled', 'title' => 'Sự kiện đã được hủy', 'body' => $event->title, 'event_id' => $event->id]));
                }
            }
            $event->update($data);
            DB::table('court_blocks')->where('game_event_id', $event->id)->update(['active' => false, 'updated_at' => now()]);
            $engine->audit($request->user(), 'event.'.$data['status'], $event->id, [], 'event');
        }, 5);

        return response()->json(['message' => 'Đã cập nhật trạng thái sự kiện.']);
    }

    private function advance(TournamentMatch $match): void
    {
        $next = TournamentMatch::where('game_event_id', $match->game_event_id)->where('round', $match->round + 1)->where('position', intdiv($match->position, 2))->first();
        if ($next) {
            $next->update([$match->position % 2 === 0 ? 'player_a' : 'player_b' => $match->winner_id]);
        }
    }

    public function draw(Request $request, GameEvent $event, BookingService $engine): JsonResponse
    {
        DB::transaction(function () use ($request, $event, $engine): void {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($event->id);
            $engine->assertManage($request->user(), $event->branch_id);
            abort_unless($event->type === 'league' && $event->status === 'published' && ! $event->matches()->exists(), 422);
            $players = $event->registrations()->where('status', 'confirmed')->pluck('user_id')->shuffle()->all();
            abort_if(count($players) < 2 || count($players) > 64, 422, 'Cần từ 2 đến 64 người đã xác nhận.');
            if (app(TournamentService::class)->format($event) !== 'single_elimination') {
                app(TournamentService::class)->draw($event, $players);
                $engine->audit($request->user(), 'event.drawn', $event->id, ['format' => $event->format], 'event');

                return;
            }
            $size = 2;
            while ($size < count($players)) {
                $size *= 2;
            }
            $players = array_pad($players, $size, null);
            for ($round = 1,$games = intdiv($size, 2); $games >= 1; $round++,$games = intdiv($games, 2)) {
                for ($position = 0; $position < $games; $position++) {
                    $event->matches()->create(['round' => $round, 'position' => $position, 'player_a' => $round === 1 ? $players[$position] : null, 'player_b' => $round === 1 ? $players[$size - 1 - $position] : null]);
                }
            }
            foreach ($event->matches()->where('round', 1)->get() as $match) {
                if (! $match->player_a || ! $match->player_b) {
                    $match->update(['winner_id' => $match->player_a ?? $match->player_b]);
                    $this->advance($match);
                }
            }
            $engine->audit($request->user(), 'event.bracket_drawn', $event->id, ['players' => $players], 'event');
        }, 5);

        return response()->json(['message' => 'Đã tạo lịch thi đấu.']);
    }

    public function score(Request $request, TournamentMatch $match, BookingService $engine): JsonResponse
    {
        $data = $request->validate(['score_a' => ['required', 'integer', 'between:0,1000'], 'score_b' => ['required', 'integer', 'between:0,1000', 'different:score_a']]);
        DB::transaction(function () use ($request, $match, $engine, $data): void {
            $event = GameEvent::query()->lockForUpdate()->findOrFail($match->game_event_id);
            $engine->assertManage($request->user(), $event->branch_id);
            $match = TournamentMatch::query()->lockForUpdate()->findOrFail($match->id);
            if ($match->winner_id && $match->score_a === (int) $data['score_a'] && $match->score_b === (int) $data['score_b']) {
                return;
            }
            abort_unless($event->status === 'published' && $match->player_a && $match->player_b && ! $match->winner_id, 422, 'Trận chưa đủ người hoặc kết quả đã chốt.');
            $match->fill($data);
            $match->winner_id = $data['score_a'] > $data['score_b'] ? $match->player_a : $match->player_b;
            $match->save();
            if (app(TournamentService::class)->format($event) === 'single_elimination') {
                $this->advance($match);
            } elseif (app(TournamentService::class)->format($event) === 'swiss') {
                app(TournamentService::class)->nextSwiss($event);
            }
            $engine->audit($request->user(), 'event.score_recorded', $match->id, $data, 'match');
        }, 5);

        return response()->json(['message' => 'Đã chốt kết quả và cập nhật vòng tiếp theo.']);
    }
}
