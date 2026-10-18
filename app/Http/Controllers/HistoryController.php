<?php

namespace App\Http\Controllers;

use App\AccountIdentity;
use App\AuditPresenter;
use App\Models\CommunityPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoryController extends Controller
{
    public function index(Request $request, string $kind): JsonResponse|StreamedResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:150', 'state' => 'nullable|string|max:50', 'per_page' => 'sometimes|integer|between:1,50', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'download' => 'sometimes|boolean']);
        abort_if(! empty($data['from']) && ! empty($data['to']) && $data['to'] < $data['from'], 422, 'Ngày kết thúc phải từ ngày bắt đầu trở đi.');
        $admin = $request->is('api/v1/admin/*');
        abort_unless(in_array($kind, $admin ? ['audits', 'reports', 'users'] : ['wallet', 'payments', 'points'], true), 404);
        [$table, $searchColumns, $statusColumn] = match ($kind) {
            'audits' => ['audit_logs', ['action', 'subject_type'], null],
            'reports' => ['content_reports', ['reason'], 'status'],
            'users' => ['users', ['name', 'email', 'phone'], 'role'],
            'wallet' => ['wallet_entries', ['description', 'kind'], 'kind'],
            'payments' => ['payment_attempts', ['order_id', 'order_info'], 'status'],
            'points' => ['loyalty_entries', ['description'], null],
        };
        $query = DB::table($table);
        if ($kind === 'payments') {
            $query->where('partner_code', 'PAYOS');
        }
        if (! $admin) {
            $query->where('user_id', $request->user()->id);
        }
        if (! empty($data['search'])) {
            $term = '%'.str_replace(['%', '_'], '', $data['search']).'%';
            $query->where(function ($query) use ($searchColumns, $term, $kind, $data): void {
                foreach ($searchColumns as $column) {
                    $query->orWhere($column, 'like', $term);
                }
                if ($kind === 'reports') {
                    $query->orWhereIn('community_post_id', CommunityPost::where('title', 'like', $term)->select('id'));
                }
                if ($kind === 'users' && preg_match('/^[+0-9\s.()-]+$/', $data['search'])) {
                    $query->orWhere('phone', 'like', '%'.AccountIdentity::phone($data['search']).'%');
                }
            });
        }
        if ($statusColumn && ! empty($data['state'])) {
            $query->where($statusColumn, $data['state']);
        }
        foreach (['from' => '>=', 'to' => '<='] as $field => $comparison) {
            if (! empty($data[$field])) {
                $query->whereDate('created_at', $comparison, $data[$field]);
            }
        }
        if ($request->boolean('download')) {
            abort_unless($admin && $kind === 'audits', 403);

            return response()->streamDownload(function () use ($query): void {
                $file = fopen('php://output', 'w');
                fwrite($file, chr(239).chr(187).chr(191));
                fputcsv($file, ['Thời gian', 'Hoạt động', 'Đối tượng', 'Chi tiết'], ',', '"', '');
                foreach ($query->orderByDesc('id')->cursor() as $row) {
                    $values = [$row->created_at, $row->action, $row->subject_type.' #'.$row->subject_id, $row->details];
                    $values = array_map(fn ($value) => preg_match('/^[=+@-]/', ltrim((string) $value)) ? "'".$value : $value, $values);
                    fputcsv($file, $values, ',', '"', '');
                }
                fclose($file);
            }, 'nhat-ky-hoat-dong.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
        }
        $columns = match ($kind) {
            'users' => ['id', 'name', 'email', 'phone', 'phone_normalized', 'role', 'branch_id', 'active', 'created_at'],
            'payments' => ['id', 'order_id', 'order_info', 'amount', 'status', 'created_at', 'expires_at', 'partner_code', 'pay_url'],
            default => ['*'],
        };
        $result = $query->orderByDesc('id')->paginate($data['per_page'] ?? 20, $columns);
        if ($kind === 'users') {
            $result->through(function ($row) {
                $row->phone_needs_review = $row->phone !== null && $row->phone_normalized === null;
                unset($row->phone_normalized);

                return $row;
            });
        }
        if ($kind === 'reports') {
            $posts = CommunityPost::whereIn('id', $result->pluck('community_post_id'))->pluck('title', 'id');
            $result->through(fn ($row) => (object) [...(array) $row, 'title' => $posts[$row->community_post_id] ?? 'Bài viết đã gỡ']);
        }
        if ($kind === 'audits') {
            $result->through(fn ($row) => app(AuditPresenter::class)->present($row));
        }

        return response()->json(['data' => $result]);
    }
}
