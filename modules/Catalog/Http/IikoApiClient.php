<?php

namespace Modules\Catalog\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IikoApiClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiLogin = null,
    ) {
    }

    public function getToken(): string
    {
        return Cache::remember('iiko_access_token', 3500, function () {
            return $this->post('/1/access_token', [
                'apiLogin' => $this->apiLogin(),
            ], withAuth: false)->json('token');
        });
    }

    public function post(string $endpoint, array $body = [], bool $withAuth = true): Response
    {
        $request = Http::retry(3, 1000)->baseUrl($this->baseUrl());

        if ($withAuth) {
            $request = $request->withToken($this->getToken());
        }

        return $request->post($endpoint, $body)->throw();
    }

    private function baseUrl(): string
    {
        return $this->baseUrl ?: (string) config('iiko.cloud_base_url');
    }

    private function apiLogin(): string
    {
        return $this->apiLogin ?: (string) config('iiko.api_login');
    }
}
