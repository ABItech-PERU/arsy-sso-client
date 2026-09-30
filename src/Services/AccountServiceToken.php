<?php

namespace Arsy\SSOClient\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Token OAuth2 client_credentials del satélite para llamadas
 * servidor-a-servidor a la Central (billing, etc.). Se cachea cifrado
 * hasta poco antes de expirar.
 */
class AccountServiceToken
{
    private const EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly HttpFactory $http,
    ) {}

    /**
     * @param  array<int, string>  $scopes
     */
    public function get(array $scopes = []): string
    {
        $cacheKey = $this->cacheKey($scopes);
        $cachedToken = $this->cache->get($cacheKey);

        if (is_string($cachedToken)) {
            return decrypt($cachedToken);
        }

        $response = $this->http
            ->asForm()
            ->acceptJson()
            ->timeout((int) config('arsy-sso.service_client.timeout', 15))
            ->post($this->tokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => config('arsy-sso.client_id'),
                'client_secret' => config('arsy-sso.client_secret'),
                'scope' => implode(' ', $this->normalize($scopes)),
            ])
            ->throw();

        $token = (string) $response->json('access_token');
        $ttl = max(self::EXPIRY_MARGIN_SECONDS, (int) $response->json('expires_in', 3600) - self::EXPIRY_MARGIN_SECONDS);

        $this->cache->put($cacheKey, encrypt($token), $ttl);

        return $token;
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public function forget(array $scopes = []): void
    {
        $this->cache->forget($this->cacheKey($scopes));
    }

    private function tokenUrl(): string
    {
        return rtrim((string) config('arsy-sso.oauth_url'), '/').'/oauth/token';
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function cacheKey(array $scopes): string
    {
        $fingerprint = config('arsy-sso.client_id').'|'.implode(' ', $this->normalize($scopes));

        return 'arsy-sso:service-token:'.hash('sha256', $fingerprint);
    }

    /**
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    private function normalize(array $scopes): array
    {
        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return $scopes;
    }
}
