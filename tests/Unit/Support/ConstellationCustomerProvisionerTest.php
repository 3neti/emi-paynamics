<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\AddBankAccount;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddCustomerWallet;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GenerateKycKybLink;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationCustomerProvisioner;

it('delegates customer provisioning through the provider actions', function (): void {
    $create = Mockery::mock(AddCustomerWallet::class);
    $details = Mockery::mock(GetWalletDetails::class);
    $bank = Mockery::mock(AddBankAccount::class);
    $kyc = Mockery::mock(GenerateKycKybLink::class);
    $create->shouldReceive('handle')->once()->with(['name' => 'A'])->andReturn(['success' => true]);
    $details->shouldReceive('handle')->once()->with('wallet-1')->andReturn(['success' => true]);
    $bank->shouldReceive('handle')->once()->with(['bank' => 'B'])->andReturn(['success' => true]);
    $kyc->shouldReceive('handle')->once()->with(['wallet' => 'W'])->andReturn(['success' => true]);
    $provider = new ConstellationCustomerProvisioner($create, $details, $bank, $kyc);

    expect($provider->createCustomerWallet(['name' => 'A']))->toBe(['success' => true])
        ->and($provider->readWallet('wallet-1'))->toBe(['success' => true])
        ->and($provider->linkBankAccount(['bank' => 'B']))->toBe(['success' => true])
        ->and($provider->createIdentityVerificationLink(['wallet' => 'W']))->toBe(['success' => true]);
});
