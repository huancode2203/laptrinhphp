<?php

namespace App;

use Illuminate\Support\Facades\DB;

class DemoCatalog
{
    public function refreshPrices(): void
    {
        $branches = DB::table('branches')->where('name', 'like', 'Badminton Hub%')->pluck('id');
        foreach ([['Cầu Yonex AS-30', 120000, 1239000], ['Pocari Sweat', 15000, 20000]] as [$name, $old, $price]) {
            DB::table('services')->whereIn('branch_id', $branches)->where('name', $name)->where('price', $old)->update(['price' => $price, 'updated_at' => now()]);
        }
        foreach ([['SHT-001', 'Yonex AS-30 (12 quả)', 120000, 1239000], ['WTR-002', 'Pocari Sweat 500ml', 15000, 20000], ['SHT-003', 'RSL Tourney (6 quả)', 55000, 150000]] as [$sku, $name, $old, $price]) {
            DB::table('products')->whereIn('branch_id', $branches)->where('sku', $sku)->where('name', $name)->where('price', $old)->update(['price' => $price, 'updated_at' => now()]);
        }
    }
}
