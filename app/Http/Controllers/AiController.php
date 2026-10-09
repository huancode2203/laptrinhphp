<?php

namespace App\Http\Controllers;

use App\CourtSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiController extends Controller
{
    public function search(Request $r, CourtSearchService $service): JsonResponse
    {
        $d = $r->validate(['message' => 'required|string|max:1000', 'filters' => 'sometimes|array']);

        return response()->json(['data' => $service->search($d['message'], $r->user(), $d['filters'] ?? null)]);
    }

    public function history(Request $r): JsonResponse
    {
        $turns = DB::table('ai_search_turns')->where('user_id', $r->user()->id)->latest('id')->limit(50)->get()->reverse()->values();
        $messages = $turns->flatMap(fn ($turn) => $this->messages($turn));

        return response()->json(['data' => $messages]);
    }

    public function chat(Request $r, CourtSearchService $service): JsonResponse
    {
        $data = $r->validate(['request_key' => 'required|uuid', 'message' => 'required|string|max:1000']);
        $turn = Cache::lock('hub-ai-chat-'.$r->user()->id, 60)->block(3, function () use ($r, $service, $data) {
            $existing = DB::table('ai_search_turns')->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $r->user()->id && $existing->message === $data['message'], 422);

                return $existing;
            }
            $previous = DB::table('ai_search_turns')->where('user_id', $r->user()->id)->latest('id')->first();
            $context = $previous ? (json_decode($previous->result, true)['filters'] ?? null) : null;
            $result = $service->search($data['message'], $r->user(), null, $context);
            $id = DB::table('ai_search_turns')->insertGetId(['user_id' => $r->user()->id, 'request_key' => $data['request_key'], 'message' => $data['message'], 'result' => json_encode($result, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('ai_search_turns')->find($id);
        });

        return response()->json(['data' => $this->messages($turn)], 201);
    }

    private function messages(object $turn): array
    {
        $result = json_decode($turn->result, true);

        return [
            ['id' => $turn->id.'-user', 'user_id' => $turn->user_id, 'user' => ['name' => 'Bạn'], 'content' => $turn->message, 'created_at' => $turn->created_at, 'deleted' => true],
            ['id' => $turn->id.'-ai', 'user_id' => null, 'user' => ['name' => 'HUB AI'], 'content' => $result['message'], 'result' => $result, 'created_at' => $turn->created_at, 'deleted' => true],
        ];
    }
}
