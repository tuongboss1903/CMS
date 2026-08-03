<?php

declare(strict_types=1);

// Phase 20 (Payment Gateway Integration, CMS-057). Cau hinh theo tung driver, dung khuon
// config/mail.php (default + drivers[]) - Zero-dependency: hash_hmac()/curl la PHP core/extension
// chuan, khong SDK Composer cua Momo/VNPay.
return [
    'return_base_url' => getenv('APP_URL') ?: 'http://localhost',
    'drivers' => [
        'momo' => [
            'endpoint' => getenv('MOMO_ENDPOINT') ?: 'https://test-payment.momo.vn/v2/gateway/api/create',
            'partner_code' => getenv('MOMO_PARTNER_CODE') ?: '',
            'access_key' => getenv('MOMO_ACCESS_KEY') ?: '',
            'secret_key' => getenv('MOMO_SECRET_KEY') ?: '',
        ],
        'vnpay' => [
            'endpoint' => getenv('VNPAY_ENDPOINT') ?: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => getenv('VNPAY_TMN_CODE') ?: '',
            'hash_secret' => getenv('VNPAY_HASH_SECRET') ?: '',
        ],
    ],
];
