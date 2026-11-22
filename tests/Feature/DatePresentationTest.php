<?php

namespace Tests\Feature;

use App\DateDisplay;
use App\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class DatePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dates_and_timestamps_use_day_month_year_in_the_business_timezone(): void
    {
        $this->assertSame('24-11-2026', DateDisplay::value('2026-11-24'));
        $this->assertSame('24-11-2026 08:00:00', DateDisplay::value('2026-11-24T01:00:00Z'));
        $this->assertSame('24-11-2026 00:30:00', DateDisplay::value('2026-11-23T17:30:00Z'));
        $this->assertSame('BH-20261124', DateDisplay::value('BH-20261124'));
        $this->assertSame(100000, DateDisplay::value(100000));
        $this->assertSame('Báo cáo 24-11-2026 – 30-11-2026', DateDisplay::text('Báo cáo 2026-11-24 – 2026-11-30'));
    }

    public function test_excel_export_formats_date_columns_without_turning_amounts_into_text(): void
    {
        $bytes = app(ReportService::class)->xlsx(['Ngày', 'Số tiền'], [['2026-11-24', 100000]]);
        $file = tempnam(sys_get_temp_dir(), 'date-export-');
        file_put_contents($file, $bytes);
        try {
            $zip = new ZipArchive;
            $zip->open($file);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString('24-11-2026', $xml);
            $this->assertStringNotContainsString('2026-11-24', $xml);
            $this->assertStringContainsString('<v>100000</v>', $xml);
        } finally {
            unlink($file);
        }
    }
}
