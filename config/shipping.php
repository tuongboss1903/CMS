<?php

declare(strict_types=1);

// Cau hinh giao hang ban kinh co dinh cho Ecommerce plugin - dung khuon config/payment.php
// (getenv() + default). Zero-dependency: cong thuc Haversine tinh khoang cach thuan PHP, khong
// goi API Google Maps/geocoding tra phi ben ngoai. shop_lat/shop_lng la toa do diem xuat phat
// giao hang (vd dia chi quan) - can chinh dung theo tung tenant khi trien khai that (hien global,
// dung tien le config/payment.php: cau hinh Ecommerce plugin hien chua tach theo tung site).
return [
    'shop_lat' => (float) (getenv('SHOP_LAT') ?: 10.7769),
    'shop_lng' => (float) (getenv('SHOP_LNG') ?: 106.7009),
    'max_radius_km' => (float) (getenv('SHOP_DELIVERY_RADIUS_KM') ?: 5),
    'fee_amount' => (float) (getenv('SHOP_DELIVERY_FEE') ?: 15000),
];
