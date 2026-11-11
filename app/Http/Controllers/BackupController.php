<?php

namespace App\Http\Controllers;

use App\BackupService;
use App\BookingService;
use App\MediaRetention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => DB::table('backup_records')->latest('id')->paginate(10, ['reference', 'purpose', 'bytes', 'summary', 'created_at'])]);
    }

    public function create(Request $request, BackupService $backup): JsonResponse
    {
        $request->validate(['password' => 'required|current_password']);
        $record = $backup->create($request->user());

        return response()->json(['data' => ['reference' => $record->reference, 'created_at' => $record->created_at]], 201);
    }

    public function download(Request $request, string $reference): Response
    {
        $request->validate(['password' => 'required|current_password']);
        $record = DB::table('backup_records')->where('reference', $reference)->first();
        abort_unless($record, 404);
        app(BookingService::class)->audit($request->user(), 'backup.downloaded', $record->id, ['reference' => $reference], 'backup');

        return Storage::disk('local')->download($record->path, 'badminton-'.$reference.'.hubbackup', ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function inspect(Request $request, string $reference, BackupService $backup): JsonResponse
    {
        $request->validate(['password' => 'required|current_password']);
        $snapshot = $backup->inspect($reference);

        return response()->json(['data' => ['created_at' => $snapshot['created_at'], 'tables' => array_map('count', $snapshot['tables']), 'maintenance_required' => true]]);
    }

    public function restore(Request $request, string $reference, BackupService $backup): JsonResponse
    {
        $request->validate(['password' => 'required|current_password', 'confirmation' => 'required|in:KHOI PHUC CSDL']);
        $result = $backup->restore($request->user(), $reference);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => $result]);
    }

    public function retention(Request $request): JsonResponse
    {
        $request->validate(['page' => 'sometimes|integer|between:1,100000']);

        return response()->json(['data' => DB::table('media_archives')->join('media_uploads', 'media_uploads.id', '=', 'media_archives.media_upload_id')->select('media_archives.id', 'media_uploads.name', 'media_uploads.size', 'media_archives.archived_at', 'media_archives.purge_after', 'media_archives.restored_at', 'media_archives.purged_at')->orderByDesc('media_archives.id')->paginate(10)]);
    }

    public function retain(Request $request, string $action): JsonResponse
    {
        $data = $request->validate(['password' => 'required|current_password', 'id' => ($action === 'archive' ? 'nullable' : 'required').'|integer|min:1']);
        if ($action === 'archive') {
            $count = app(MediaRetention::class)->archiveUnused();
        } else {
            abort_unless($request->filled('id'), 422);
            match ($action) {
                'restore' => app(MediaRetention::class)->restore((int) $data['id']),
                'purge' => app(MediaRetention::class)->purge((int) $data['id']),
                default => abort(404),
            };
            $count = 1;
        }
        app(BookingService::class)->audit($request->user(), 'media.'.$action, (int) ($data['id'] ?? 0), ['count' => $count], 'media');

        return response()->json(['data' => ['count' => $count]]);
    }
}
