<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiCore\Contracts\ProviderCustomerProvisioner;
use LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\AddBankAccount;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddCustomerWallet;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GenerateKycKybLink;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;

final readonly class ConstellationCustomerProvisioner implements ProviderCustomerProvisioner
{
    public function __construct(
        private AddCustomerWallet $createWallet,
        private GetWalletDetails $walletDetails,
        private AddBankAccount $bankAccount,
        private GenerateKycKybLink $identityLink,
    ) {}

    public function providerCode(): string
    {
        return 'paynamics_constellation';
    }

    public function createCustomerWallet(array $request): array
    {
        return $this->createWallet->handle($request);
    }

    public function readWallet(string $identifier): array
    {
        return $this->walletDetails->handle($identifier);
    }

    public function linkBankAccount(array $request): array
    {
        return $this->bankAccount->handle($request);
    }

    public function createIdentityVerificationLink(array $request): array
    {
        return $this->identityLink->handle($request);
    }
}
