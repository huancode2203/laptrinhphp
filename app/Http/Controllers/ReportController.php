<?php

namespace App\Http\Controllers;

use App\AccessService;
use App\BookingService;
use App\Models\Booking;
use App\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    private function data(Request $r): array
    {
        $d = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'branch_id' => 'nullable|integer|exists:branches,id']);
        abort_if(CarbonImmutable::parse($d['from'])->diffInDays(CarbonImmutable::parse($d['to'])) > 366, 422);
        $branch = $r->filled('branch_id') ? $r->integer('branch_id') : ($r->user()->role === 'admin' ? null : $r->user()->branch_id);
        if ($branch) {
            abort_unless(app(AccessService::class)->manages($r->user(), $branch), 403);
        }

        return app(ReportService::class)->data($d['from'], $d['to'], $branch);
    }

    public function index(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->data($r)]);
    }

    public function export(Request $r, string $format): Response
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        $d = $this->data($r);
        $rows = [];
        foreach (['daily' => 'Ngày', 'by_branch' => 'Chi nhánh', 'by_court' => 'Sân', 'by_product' => 'Sản phẩm', 'by_service' => 'Dịch vụ'] as $group => $label) {
            foreach ($d[$group] as $v) {
                $rows[] = [$label, $v['name'] ?? $v['date'], $v['revenue']];
            }
        }
        $rows[] = ['Tổng', 'Đã thu', $d['collected']];
        $rows[] = ['Tổng', 'Đã hoàn', $d['refunded']];
        $rows[] = ['Tổng', 'Doanh thu ròng', $d['net_revenue']];
        $service = app(ReportService::class);
        $headers = ['Nhóm', 'Nội dung', 'VND'];
        $bytes = $format === 'pdf' ? $service->pdf('Báo cáo '.$d['from'].' – '.$d['to'], $headers, $rows) : $service->xlsx($headers, $rows);

        return response($bytes, 200, ['Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="badminton-report.'.$format.'"']);
    }

    public function receipt(Request $r, Booking $booking): Response
    {
        app(BookingController::class)->show($r, $booking, app(BookingService::class));
        $rows = [['Mã lịch', $booking->reference], ['Chi nhánh', $booking->court->branch->name], ['Sân', $booking->court->name], ['Thời gian', $booking->starts_at->format('d/m/Y H:i').' – '.$booking->ends_at->format('H:i')], ['Phí sân', number_format($booking->court_amount).' VND'], ['Dịch vụ', number_format($booking->service_amount).' VND'], ['Hàng tại quầy', number_format($booking->product_amount).' VND'], ['Ưu đãi', number_format($booking->discount_amount).' VND'], ['Tổng', number_format($booking->total_amount).' VND'], ['Đã thu', number_format($booking->paid_amount).' VND'], ['Còn lại', number_format(max(0, $booking->total_amount - $booking->paid_amount)).' VND']];

        foreach ($booking->sales()->with('items')->get()->flatMap(fn ($s) => $s->items) as $item) {
            $rows[] = [$item->name.' x '.$item->quantity, number_format($item->amount).' VND'];
        }

        return response(app(ReportService::class)->pdf('Hóa đơn '.$booking->reference, ['Nội dung', 'Chi tiết'], $rows), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$booking->reference.'.pdf"']);
    }
}
