<?php

/*
 | SỬA 18/10 — đơn vị token (xem App\Support\TokenPrice). Dữ liệu lưu bằng đồng, hiển thị chia cho tỉ giá này:
 | mặc định 1.000đ = 1 token (giá 200.000đ -> 200 token, nạp 100.000đ -> 100 token).
 */
return [
    'vnd_per_token' => (int) env('TOKEN_VND_RATE', 1000),
];
