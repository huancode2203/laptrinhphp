<?php

namespace App\Http\Controllers;

use App\CourtSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function search(Request $r, CourtSearchService $service): JsonResponse
    {
        $d = $r->validate(['message' => 'required|string|max:1000', 'filters' => 'sometimes|array']);

        return response()->json(['data' => $service->search($d['message'], $r->user(), $d['filters'] ?? null)]);
    }
}
