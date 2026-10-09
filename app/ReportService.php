<?php

namespace App;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function data(string $from, string $to, ?int $branch): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->endOfDay();
        $bookings = Booking::whereBetween('starts_at', [$start, $end])->whereHas('court', fn ($q) => $q->when($branch, fn ($q) => $q->where('branch_id', $branch)))->with('court.branch', 'services')->get();
        $courtPayments = Payment::whereBetween('created_at', [$start, $end])->whereHas('booking.court', fn ($q) => $q->when($branch, fn ($q) => $q->where('branch_id', $branch)))->with('booking.court.branch', 'booking.services')->get();
        $commerce = DB::table('commerce_payments')->whereBetween('created_at', [$start, $end])->when($branch, fn ($q) => $q->where('branch_id', $branch))->get();
        $daily = [];
        $byBranch = [];
        $byCourt = [];
        $byService = [];
        $byProduct = [];
        $methods = [];
        $collected = 0;
        $refunded = 0;
        $pos = 0;
        foreach ($courtPayments->concat($commerce) as $p) {
            $value = $p->kind === 'refund' ? -$p->amount : $p->amount;
            $date = substr((string) $p->created_at, 0, 10);
            $daily[$date] = ($daily[$date] ?? 0) + $value;
            $methods[$p->method] = ($methods[$p->method] ?? 0) + $value;
            $bid = $p instanceof Payment ? $p->booking->court->branch_id : $p->branch_id;
            $byBranch[$bid] = ($byBranch[$bid] ?? 0) + $value;
            if ($value >= 0) {
                $collected += $value;
            } else {
                $refunded -= $value;
            }
            if ($p instanceof Payment) {
                $b = $p->booking;
                $cid = $b->court_id;
                $byCourt[$cid] = ($byCourt[$cid] ?? 0) + $value;
                foreach ($b->services as $s) {
                    $byService[$s->pivot->name] = ($byService[$s->pivot->name] ?? 0) + ($b->total_amount ? (int) round($value * $s->pivot->amount / $b->total_amount) : 0);
                }
            } elseif ($p->subject_type === 'sale') {
                $pos += $value;
                foreach (DB::table('sale_items')->where('sale_id', $p->subject_id)->get() as $item) {
                    $byProduct[$item->name] = ($byProduct[$item->name] ?? 0) + ($p->kind === 'refund' ? -$item->amount : $item->amount);
                }
            }
        }
        $branches = Branch::when($branch, fn ($q) => $q->where('id', $branch))->get()->keyBy('id');
        $courts = Court::when($branch, fn ($q) => $q->where('branch_id', $branch))->with('branch')->get()->keyBy('id');
        $days = (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
        $occupancy = [];
        $heat = [];
        $types = [];
        $minutes = 0;
        $available = 0;
        foreach ($courts as $c) {
            if ($c->status !== 'active') {
                continue;
            } $potential = ($c->branch->closes_minute - $c->branch->opens_minute) * $days;
            $available += $potential;
            $occupancy[$c->id] = ['name' => $c->branch->district.' · '.$c->name, 'minutes' => 0, 'available_minutes' => $potential];
        }
        foreach ($bookings->where('status', '!=', 'cancelled') as $b) {
            $played = (int) $b->starts_at->diffInMinutes($b->ends_at);
            if (isset($occupancy[$b->court_id])) {
                $minutes += $played;
            }
            if (isset($occupancy[$b->court_id])) {
                $occupancy[$b->court_id]['minutes'] += $played;
            }
            $types[$b->booking_type === 'fixed' ? 'fixed' : $b->source] = ($types[$b->booking_type === 'fixed' ? 'fixed' : $b->source] ?? 0) + 1;
            for ($t = $b->starts_at; $t->lt($b->ends_at); $t = $t->addMinutes(30)) {
                $k = $t->dayOfWeek.'-'.$t->hour;
                $heat[$k] = ($heat[$k] ?? 0) + 1;
            }
        }
        $net = $collected - $refunded;
        ksort($daily);

        return ['from' => $from, 'to' => $to, 'collected' => $collected, 'refunded' => $refunded, 'net_revenue' => $net, 'pos_revenue' => $pos, 'bookings' => $bookings->count(), 'cancelled' => $bookings->where('status', 'cancelled')->count(), 'cancellation_rate' => $bookings->count() ? round(100 * $bookings->where('status', 'cancelled')->count() / $bookings->count(), 1) : 0,
            'outstanding' => $bookings->where('status', '!=', 'cancelled')->sum(fn ($b) => max(0, $b->total_amount - $b->paid_amount)),
            'occupancy_percent' => $available ? round(100 * $minutes / $available, 1) : 0, 'average_order' => $bookings->where('status', '!=', 'cancelled')->count() ? round($bookings->where('status', '!=', 'cancelled')->avg('total_amount')) : 0,
            'daily' => collect($daily)->map(fn ($v, $k) => ['date' => $k, 'revenue' => $v])->values()->all(), 'methods' => $methods,
            'by_branch' => collect($byBranch)->map(fn ($v, $k) => ['name' => $branches[$k]->name ?? 'Toàn hệ thống', 'revenue' => $v])->values()->all(),
            'by_court' => collect($byCourt)->map(fn ($v, $k) => ['name' => isset($courts[$k]) ? $courts[$k]->branch->district.' · '.$courts[$k]->name : '#'.$k, 'revenue' => $v])->values()->all(),
            'by_service' => collect($byService)->map(fn ($v, $k) => ['name' => $k, 'revenue' => $v])->values()->all(), 'by_product' => collect($byProduct)->map(fn ($v, $k) => ['name' => $k, 'revenue' => $v])->values()->all(),
            'occupancy' => collect($occupancy)->map(fn ($v) => $v + ['percent' => $v['available_minutes'] ? round(100 * $v['minutes'] / $v['available_minutes'], 1) : 0])->values()->all(),
            'heatmap' => collect($heat)->map(function ($v, $k) {
                [$d,$h] = explode('-', $k);

                return ['weekday' => (int) $d, 'hour' => (int) $h, 'slots' => $v];
            })->values()->all(), 'booking_types' => $types];
    }

    public function pdf(string $title, array $headers, array $rows): string
    {
        $html = '<html><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:10px;color:#0d1f2d}h1{font-size:18px}table{border-collapse:collapse;width:100%}th{background:#e9fbd7}td,th{border:1px solid #dce4dc;padding:7px;text-align:left}</style><h1>'.e($title).'</h1><p>Badminton Hub · '.now()->format('d/m/Y H:i').'</p><table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>'.e($h).'</th>';
        } $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $v) {
                $html .= '<td>'.e((string) $v).'</td>';
            }$html .= '</tr>';
        }
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html.'</tbody></table></html>', 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function xlsx(array $headers, array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'hub-xlsx-');
        $zip = new \ZipArchive;
        $zip->open($file, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Báo cáo" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers, ...$rows] as $index => $row) {
            $sheet .= '<row r="'.($index + 1).'">';
            foreach ($row as $v) {
                $sheet .= is_numeric($v) && $index > 0 ? '<c><v>'.$v.'</v></c>' : '<c t="inlineStr"><is><t>'.htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }$sheet .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet.'</sheetData></worksheet>');
        $zip->close();
        $bytes = file_get_contents($file);
        unlink($file);

        return $bytes;
    }
}
