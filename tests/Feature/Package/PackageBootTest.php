<?php

use LBHurtado\EmiCore\Contracts\SignsProviderPayloads;
use LBHurtado\EmiCore\Contracts\VerifiesProviderPostbacks;
use LBHurtado\EmiPaynamicsConstellation\ConstellationServiceProvider;
use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSignatureVerifier;

it('boots the constellation service provider', function () {
    expect(app()->getProviders(ConstellationServiceProvider::class))
        ->not->toBeEmpty();
});

it('loads the constellation config', function () {
    expect(config('constellation.base_url'))
        ->toBe('https://asterism.payserv.net/v1');
});

it('binds SignsProviderPayloads to ConstellationSigner', function () {
    expect(app(SignsProviderPayloads::class))
        ->toBeInstanceOf(ConstellationSigner::class);
});

it('binds VerifiesProviderPostbacks to ConstellationSignatureVerifier', function () {
    expect(app(VerifiesProviderPostbacks::class))
        ->toBeInstanceOf(ConstellationSignatureVerifier::class);
});

it('can instantiate ConstellationClient', function () {
    expect(new ConstellationClient)->toBeInstanceOf(ConstellationClient::class);
});
