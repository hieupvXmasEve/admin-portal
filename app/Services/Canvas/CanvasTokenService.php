<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Models\CanvasIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class CanvasTokenService
{
    /**
     * Check if token is expired or about to expire (within 5 minutes)
     */
    public function isTokenExpired(CanvasIntegration $integration): bool
    {
        if (! $integration->token_expires_at) {
            return true;
        }

        // Clone to avoid mutating the original
        return $integration->token_expires_at->copy()->subMinutes(5)->isPast();
    }

    /**
     * Refresh access token if needed
     */
    public function refreshIfNeeded(CanvasIntegration $integration): bool
    {
        if (! $this->isTokenExpired($integration)) {
            return false;
        }

        if (! $integration->refresh_token) {
            Log::warning('Cannot refresh token: no refresh token available', [
                'integration_id' => $integration->id,
            ]);
            return false;
        }

        return $this->refreshToken($integration);
    }

    /**
     * Refresh the access token using refresh token
     */
    public function refreshToken(CanvasIntegration $integration): bool
    {
        try {
            $client = new Client([
                'base_uri' => rtrim($integration->canvas_url, '/') . '/',
                'timeout' => 30,
            ]);

            $response = $client->post('login/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $integration->client_id,
                    'client_secret' => $integration->client_secret,
                    'refresh_token' => $integration->refresh_token,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            $integration->update([
                'access_token' => $data['access_token'],
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            Log::info('Canvas access token refreshed successfully', [
                'integration_id' => $integration->id,
            ]);

            return true;
        } catch (GuzzleException $e) {
            Log::error('Failed to refresh Canvas access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(
        CanvasIntegration $integration,
        string $code,
        string $redirectUri
    ): bool {
        try {
            $client = new Client([
                'base_uri' => rtrim($integration->canvas_url, '/') . '/',
                'timeout' => 30,
            ]);

            Log::info('Exchanging authorization code', [
                'integration_id' => $integration->id,
                'canvas_url' => $integration->canvas_url,
                'client_id' => $integration->client_id,
                'redirect_uri' => $redirectUri,
            ]);

            $response = $client->post('login/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $integration->client_id,
                    'client_secret' => $integration->client_secret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ],
            ]);

            $responseBody = (string) $response->getBody();
            $data = json_decode($responseBody, true);

            Log::info('Canvas token response received', [
                'integration_id' => $integration->id,
                'has_access_token' => isset($data['access_token']),
                'has_refresh_token' => isset($data['refresh_token']),
                'expires_in' => $data['expires_in'] ?? null,
                'token_type' => $data['token_type'] ?? null,
                'user_id' => $data['user']['id'] ?? null,
                'access_token_length' => isset($data['access_token']) ? strlen($data['access_token']) : 0,
                'access_token_preview' => isset($data['access_token']) ? substr($data['access_token'], 0, 20) . '...' : null,
            ]);

            if (!isset($data['access_token'])) {
                Log::error('No access token in Canvas response', [
                    'integration_id' => $integration->id,
                    'response' => $data,
                ]);
                return false;
            }

            $integration->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            Log::info('Canvas OAuth token exchanged and saved successfully', [
                'integration_id' => $integration->id,
                'user_id' => $data['user']['id'] ?? null,
            ]);

            return true;
        } catch (GuzzleException $e) {
            Log::error('Failed to exchange Canvas authorization code', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
                'response' => method_exists($e, 'getResponse') && $e->getResponse() 
                    ? (string) $e->getResponse()->getBody() 
                    : null,
            ]);

            return false;
        }
    }

    /**
     * Revoke the access token
     */
    public function revokeToken(CanvasIntegration $integration): bool
    {
        if (! $integration->access_token) {
            return true;
        }

        try {
            $client = new Client([
                'base_uri' => rtrim($integration->canvas_url, '/') . '/',
                'timeout' => 30,
            ]);

            $client->delete('login/oauth2/token', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $integration->access_token,
                ],
            ]);

            $integration->update([
                'access_token' => null,
                'refresh_token' => null,
                'token_expires_at' => null,
            ]);

            Log::info('Canvas access token revoked', [
                'integration_id' => $integration->id,
            ]);

            return true;
        } catch (GuzzleException $e) {
            Log::error('Failed to revoke Canvas access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
