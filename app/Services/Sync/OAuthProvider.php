<?php

namespace App\Services\Sync;

use App\Models\CalendarConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google and Outlook: the person signs in with them once (OAuth), Tribe keeps the refresh token
 * (encrypted) and gets a fresh access token whenever the old one is about to run out.
 */
abstract class OAuthProvider implements CalendarProvider
{
    abstract protected function key(): string;

    abstract protected function authorizeEndpoint(): string;

    abstract protected function tokenEndpoint(): string;

    abstract protected function scopes(): string;

    /** @return array<string, string> */
    protected function authorizeParameters(): array
    {
        return [];
    }

    /** The email address of the account, for showing which calendar is connected. */
    abstract protected function account(string $accessToken): ?string;

    public function authorizeUrl(string $state, string $redirect): string
    {
        return $this->authorizeEndpoint().'?'.http_build_query([
            'client_id' => config("services.{$this->key()}.client_id"),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => $this->scopes(),
            'state' => $state,
        ] + $this->authorizeParameters());
    }

    /** @return array{account: string|null, credentials: array<string, mixed>} */
    public function connect(string $code, string $redirect): array
    {
        $tokens = $this->tokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
        ]);

        if (empty($tokens['refresh_token'])) {
            throw new \RuntimeException(__(':provider het nie toegang gegee wat hou nie. Probeer weer.', ['provider' => CalendarConnection::label($this->key())]));
        }

        return ['account' => $this->account((string) $tokens['access_token']), 'credentials' => $tokens];
    }

    protected function http(CalendarConnection $connection): PendingRequest
    {
        return Http::timeout(20)->acceptJson()->withToken($this->accessToken($connection));
    }

    /** @throws \RuntimeException with a readable reason when the calendar says no */
    protected function check(Response $response): Response
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new \RuntimeException(__(':provider het toegang geweier. Koppel die kalender weer.', ['provider' => CalendarConnection::label($this->key())]));
        }
        if (! $response->successful()) {
            throw new \RuntimeException(__(':provider het nie geantwoord nie (fout :status).', ['provider' => CalendarConnection::label($this->key()), 'status' => $response->status()]));
        }

        return $response;
    }

    private function accessToken(CalendarConnection $connection): string
    {
        $credentials = $connection->credentials;

        if (($credentials['expires_at'] ?? 0) > time() + 60 && ! empty($credentials['access_token'])) {
            return (string) $credentials['access_token'];
        }

        $fresh = $this->tokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) ($credentials['refresh_token'] ?? ''),
        ]);

        // Google keeps the old refresh token; Microsoft hands out a new one each time.
        $connection->credentials = $fresh + $credentials;
        $connection->save();

        return (string) $fresh['access_token'];
    }

    /**
     * @param  array<string, string>  $grant
     * @return array<string, mixed>
     */
    private function tokens(array $grant): array
    {
        try {
            $response = Http::timeout(20)->asForm()->acceptJson()->post($this->tokenEndpoint(), $grant + [
                'client_id' => config("services.{$this->key()}.client_id"),
                'client_secret' => config("services.{$this->key()}.client_secret"),
            ]);
        } catch (\Throwable) {
            throw new \RuntimeException(__(':provider het nie geantwoord nie.', ['provider' => CalendarConnection::label($this->key())]));
        }

        $tokens = $response->json();
        if (! $response->successful() || ! is_array($tokens) || empty($tokens['access_token'])) {
            throw new \RuntimeException(__('Tribe het nie meer toegang tot die :provider-kalender nie. Koppel dit weer.', ['provider' => CalendarConnection::label($this->key())]));
        }

        $tokens['expires_at'] = time() + (int) ($tokens['expires_in'] ?? 3600);

        return array_intersect_key($tokens, array_flip(['access_token', 'refresh_token', 'expires_at']));
    }
}
