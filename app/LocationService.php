<?php

namespace App;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationService
{
    public function normalize(string $area): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/^(phuong|xa|p\.|x\.)\s*/', '', Str::lower(Str::ascii($area, 'vi')))));
    }

    public function directions(Branch $branch): string
    {
        $destination = $branch->latitude !== null && $branch->longitude !== null ? $branch->latitude.','.$branch->longitude : $branch->address;

        return 'https://www.google.com/maps/dir/?'.http_build_query(array_filter(['api' => 1, 'destination' => $destination, 'destination_place_id' => $branch->google_place_id, 'travelmode' => 'driving']));
    }

    /** Updates only recognizable teaching fixtures; never infers a ward from an entire former district. */
    public function updateDemoAddresses(): void
    {
        $base = ['18 Nguyễn Bỉnh Khiêm, Q.1' => 'Phường Sài Gòn', '210 Võ Văn Tần, Q.3' => 'Phường Bàn Cờ', '72 Điện Biên Phủ, Bình Thạnh' => 'Phường Thạnh Mỹ Tây', '93 Phan Xích Long, Phú Nhuận' => 'Phường Cầu Kiệu', '45 Quang Trung, Gò Vấp' => 'Phường Hạnh Thông', '110 Cộng Hòa, Tân Bình' => 'Phường Tân Bình', '52 Võ Văn Ngân, Thủ Đức' => 'Phường Thủ Đức', '88 Nguyễn Hữu Thọ, Q.7' => 'Phường Tân Hưng'];
        foreach ($base as $address => $ward) {
            DB::table('branches')->where('address', $address)->where('name', 'like', 'Badminton Hub%')->whereNull('ward')->update(['ward' => $ward, 'address' => explode(',', $address)[0].', '.$ward.', Thành phố Hồ Chí Minh']);
        }
        $expanded = ['Thành Thái' => 'Phường Diên Hồng', 'Lạc Long Quân' => 'Phường Hòa Bình', 'Khánh Hội' => 'Phường Khánh Hội', 'An Dương Vương' => 'Phường An Đông', 'Bình Phú' => 'Phường Bình Phú', 'Phạm Hùng' => 'Phường Chánh Hưng', 'Tân Thới Hiệp' => 'Phường Tân Thới Hiệp', 'Tên Lửa' => 'Phường An Lạc', 'Lũy Bán Bích' => 'Phường Tân Phú', 'Phú Xuân' => 'Xã Nhà Bè', 'Phong Phú' => 'Xã Bình Hưng', 'Bà Điểm' => 'Xã Bà Điểm', 'Tân Phú Trung' => 'Xã Củ Chi', 'An Phú' => 'Phường An Khánh', 'Thanh Đa' => 'Phường Bình Quới', 'Bàu Cát' => 'Phường Tân Bình', 'Tân Phong' => 'Phường Tân Hưng', 'Phường An Hội' => 'Phường An Hội Tây', 'Linh Trung' => 'Phường Linh Xuân', 'Vĩnh Lộc' => 'Xã Vĩnh Lộc', 'Sơn Kỳ' => 'Phường Tân Phú', 'An Lạc' => 'Phường An Lạc'];
        foreach ($expanded as $location => $ward) {
            DB::table('branches')->where('name', 'Badminton Hub – '.$location)->where('address', 'like', 'Khu thể thao '.$location.',%')->whereNull('ward')->update(['ward' => $ward, 'address' => 'Khu thể thao '.$location.', '.$ward.', Thành phố Hồ Chí Minh']);
        }
    }
}
