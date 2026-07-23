<?php

use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\InteractiveOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\NullOtpResolver;

return [
    'base_url' => env('CONSTELLATION_BASE_URL', 'https://asterism.payserv.net/v1'),
    'username' => env('CONSTELLATION_USERNAME'),
    'password' => env('CONSTELLATION_PASSWORD'),
    'merchant_key' => env('CONSTELLATION_MERCHANT_KEY'),
    'notification_url' => env('CONSTELLATION_NOTIFICATION_URL'),
    'log_channel' => env('CONSTELLATION_LOG_CHANNEL', 'constellation'),
    'phantom_ttl_minutes' => env('CONSTELLATION_PHANTOM_TTL_MINUTES', 60),

    'settlement_wallet_id' => env('CONSTELLATION_SETTLEMENT_WALLET_ID'),
    'revenue_wallet_id' => env('CONSTELLATION_REVENUE_WALLET_ID'),
    'default_issuer_wallet_id' => env('CONSTELLATION_DEFAULT_ISSUER_WALLET_ID'),

    'funding' => [
        'wallet_id' => env('CONSTELLATION_FUNDING_WALLET_ID', env('CONSTELLATION_SETTLEMENT_WALLET_ID')),
        'payment_method' => env('CONSTELLATION_FUNDING_PAYMENT_METHOD', 'onlinebanktransfer'),
        'payment_channel' => env('CONSTELLATION_FUNDING_PAYMENT_CHANNEL', 'ubp_online'),
        'response_url' => env('CONSTELLATION_FUNDING_RESPONSE_URL', env('CONSTELLATION_NOTIFICATION_URL')),
        'cancel_url' => env('CONSTELLATION_FUNDING_CANCEL_URL', env('CONSTELLATION_NOTIFICATION_URL')),
        'settled_statuses' => ['SUCCESS', 'COMPLETED', 'PAID'],
        'pending_statuses' => ['PENDING', 'PROCESSING', 'INITIATED'],
        'reversed_statuses' => ['REVERSED', 'REFUNDED', 'CANCELLED', 'FAILED', 'EXPIRED'],
        'not_observed_response_codes' => [],
    ],

    'settlement' => [
        'email' => env('CONSTELLATION_SETTLEMENT_EMAIL', 'admin@disburse.cash'),
        'notification_url' => env('CONSTELLATION_SETTLEMENT_NOTIFICATION_URL', 'https://eoqaievfdukh4qb.m.pipedream.net'),
    ],

    'revenue' => [
        'email' => env('CONSTELLATION_REVENUE_EMAIL', '3neti@lyflyn.net'),
        'notification_url' => env('CONSTELLATION_REVENUE_NOTIFICATION_URL', 'https://eo8i9z5jvsy2mf8.m.pipedream.net'),
    ],

    'company' => [
        'name' => env('CONSTELLATION_COMPANY_NAME', '3neti Research and Development OPC'),
        'tin' => env('CONSTELLATION_TIN', '777-324-175'),
        'email' => env('CONSTELLATION_EMAIL', 'lester@hurtado.ph'),
        'mobile_no' => env('CONSTELLATION_MOBILE_NO', '639173011987'),
        'website' => env('CONSTELLATION_WEBSITE', ''),
        'account_first_name' => env('CONSTELLATION_ACCOUNT_FIRST_NAME', 'Lester'),
        'account_middle_name' => env('CONSTELLATION_ACCOUNT_MIDDLE_NAME', 'Biadora'),
        'account_last_name' => env('CONSTELLATION_ACCOUNT_LAST_NAME', 'Hurtado'),
        'birthdate' => env('CONSTELLATION_BIRTHDATE', '1970-04-21'),
        'nationality' => env('CONSTELLATION_NATIONALITY', 'Filipino'),
        'source_of_funds' => env('CONSTELLATION_SOURCE_OF_FUNDS', 'Family Savings'),
        'business_address' => env('CONSTELLATION_BUSINESS_ADDRESS', 'E1504 Philippine Stock Exchange Centre, Exchange Road'),
        'business_zip' => env('CONSTELLATION_BUSINESS_ZIP', '1605'),
        'business_city' => env('CONSTELLATION_BUSINESS_CITY', 'Pasig City'),
        'business_state' => env('CONSTELLATION_BUSINESS_STATE', 'Metro Manila'),
        'business_country' => env('CONSTELLATION_BUSINESS_COUNTRY', 'PH'),
        'success_url' => env('CONSTELLATION_SUCCESS_URL', ''),
        'failed_url' => env('CONSTELLATION_FAILED_URL', ''),
    ],

    'rail_fees' => [
        'INSTAPAY' => env('CONSTELLATION_INSTAPAY_FEE', 0),
        'PESONET' => env('CONSTELLATION_PESONET_FEE', 0),
    ],

    'bank_map' => [
        // 'UBP' => 'bank-123',
        // 'BDO' => 'bank-456',
    ],

    'otp' => [
        'resolver' => env('CONSTELLATION_OTP_RESOLVER', 'interactive'),

        'resolvers' => [
            'interactive' => InteractiveOtpResolver::class,
            'null' => NullOtpResolver::class,
            'deferred' => DeferredOtpResolver::class,
        ],
    ],
];
