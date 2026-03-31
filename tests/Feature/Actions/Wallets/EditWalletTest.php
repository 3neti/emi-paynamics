<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\EditWallet;

it('sends only provided fields using PATCH semantics', function () {
    Http::fake([
        '*/edit_wallet/*' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTABCDEF',
                'status' => 'Active',
                'address' => '456 New St',
                'city' => 'Quezon City',
            ],
        ]),
    ]);

    $result = EditWallet::run('CNSTWLLTABCDEF', [
        'address' => '456 New St',
        'city' => 'Quezon City',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['address'])->toBe('456 New St');

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), 'edit_wallet/CNSTWLLTABCDEF')
            && $request['address'] === '456 New St'
            && $request['signature'] !== null;
    });
});
