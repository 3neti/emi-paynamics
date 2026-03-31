<?php

namespace LBHurtado\EmiPaynamicsConstellation\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ConstellationClient
{
    public function baseRequest(): PendingRequest
    {
        return Http::baseUrl(config('constellation.base_url'))
            ->withBasicAuth(
                config('constellation.username'),
                config('constellation.password'),
            )
            ->acceptJson()
            ->asJson();
    }

    public function get(string $endpoint): Response
    {
        return $this->baseRequest()->get($endpoint);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        return $this->baseRequest()->post($endpoint, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->baseRequest()->patch($endpoint, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        return $this->baseRequest()->put($endpoint, $data);
    }

    public function delete(string $endpoint): Response
    {
        return $this->baseRequest()->delete($endpoint);
    }
}
