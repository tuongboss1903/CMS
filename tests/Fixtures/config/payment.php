<?php

declare(strict_types=1);

// Phase 20 (CMS-057) - secret co dinh cho test (khong doc getenv()) de HMAC test deterministic.
return [
    'return_base_url' => 'https://tenant-a.example.com',
    'drivers' => [
        'momo' => [
            'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'partner_code' => 'TEST_PARTNER',
            'access_key' => 'test-access-key',
            'secret_key' => 'test-momo-secret',
        ],
        'vnpay' => [
            'endpoint' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TEST_TMN',
            'hash_secret' => 'test-vnpay-secret',
        ],
    ],
];
