<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\MomoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MomoController extends Controller
{
    public function create(Request $r, MomoService $m): JsonResponse
    {
        $d = $r->validate(['deposit_only' => 'sometimes|boolean', 'request_key' => ['required', 'uuid'], 'purpose' => ['required', Rule::in(['booking', 'wallet'])], 'booking_id' => ['exclude_unless:purpose,booking', 'required', 'integer'], 'amount' => ['exclude_unless:purpose,wallet', 'required', 'integer', 'min:1000', 'max:50000000']]);

        return response()->json(['data' => $m->create($r->user(), $d)]);
    }

    public function status(Request $r, PaymentAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->user_id === $r->user()->id, 403);

        return response()->json(['data' => $attempt]);
    }

    public function ipn(Request $r, MomoService $m): Response
    {
        $r->validate(['amount' => ['required', 'integer'], 'transId' => ['required'], 'resultCode' => ['required', 'integer'], 'signature' => ['required', 'string', 'size:64']]);
        $m->callback($r->all());

        return response()->noContent();
    }
}
