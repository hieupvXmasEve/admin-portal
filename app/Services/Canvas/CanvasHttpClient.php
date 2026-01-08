<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Exceptions\CanvasConnectionException;
use App\Models\CanvasIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class CanvasHttpClient
{
    private Client $client;
    private CanvasIntegration $integration;
    private ?CanvasTokenService $tokenService = null;

    /**
     * Maximum retries for API requests on timeout
     */
    private const MAX_API_RETRIES = 2;

    public function __construct(CanvasIntegration $integration, ?CanvasTokenService $tokenService = null)
    {
        $this->integration = $integration;
        $this->tokenService = $tokenService;

        $this->client = new Client([
            'base_uri' => rtrim($integration->canvas_url, '/') . '/',
            'timeout' => config('services.canvas.timeout', 120), // Increased for bulk operations
            'connect_timeout' => 15, // Separate timeout for DNS + connection
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Force IPv4 to avoid IPv6 DNS issues
                CURLOPT_DNS_CACHE_TIMEOUT => 120, // Cache DNS for 2 minutes
            ],
        ]);
    }

    /**
     * Check if the integration is active before making requests
     *
     * @throws CanvasConnectionException
     */
    public function ensureIntegrationActive(): void
    {
        // Refresh the integration to get the latest status
        $this->integration->refresh();

        if (!$this->integration->is_active) {
            throw CanvasConnectionException::integrationInactive($this->integration->id);
        }
    }

    /**
     * Make GET request to Canvas API
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $params]);
    }

    /**
     * Make POST request to Canvas API
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Make PUT request to Canvas API
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Make DELETE request to Canvas API
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Make request with auto token refresh on 401 and retry on timeout
     *
     * @throws CanvasConnectionException
     * @throws GuzzleException
     */
    private function request(string $method, string $endpoint, array $options = []): array
    {
        // Check if integration is still active before making request
        $this->ensureIntegrationActive();

        // Refresh token if expired
        if ($this->tokenService && $this->integration->isTokenExpired()) {
            $this->tokenService->refreshIfNeeded($this->integration);
            $this->integration->refresh();
        }

        // Add Authorization header
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            [
                'Authorization' => 'Bearer ' . $this->integration->access_token,
            ]
        );

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_API_RETRIES; $attempt++) {
            try {
                $response = $this->client->request($method, $endpoint, $options);
                $body = (string) $response->getBody();

                return json_decode($body, true) ?? [];
            } catch (ConnectException $e) {
                $lastException = $e;
                $isTimeout = str_contains($e->getMessage(), 'cURL error 28') ||
                    str_contains($e->getMessage(), 'Connection timed out');

                Log::warning('Canvas API connection failed', [
                    'integration_id' => $this->integration->id,
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'attempt' => $attempt,
                    'is_timeout' => $isTimeout,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_API_RETRIES) {
                    // Wait before retry (exponential backoff)
                    sleep($attempt * 2);
                    continue;
                }

                // All retries exhausted - deactivate integration if timeout
                if ($isTimeout && $this->tokenService) {
                    $this->tokenService->deactivateIntegration(
                        $this->integration,
                        "API request timed out after {$attempt} attempts on endpoint: {$endpoint}"
                    );
                    throw CanvasConnectionException::timeout(
                        $this->integration->id,
                        $endpoint,
                        config('services.canvas.timeout', 120)
                    );
                }

                throw $e;
            } catch (GuzzleException $e) {
                // If 401, try to refresh token once
                if ($e->getCode() === 401 && $this->tokenService) {
                    Log::warning('Canvas API returned 401, attempting token refresh', [
                        'integration_id' => $this->integration->id,
                        'endpoint' => $endpoint,
                    ]);

                    try {
                        $this->tokenService->refreshToken($this->integration);
                        $this->integration->refresh();

                        // Retry request with new token
                        $options['headers']['Authorization'] = 'Bearer ' . $this->integration->access_token;
                        $response = $this->client->request($method, $endpoint, $options);
                        $body = (string) $response->getBody();

                        return json_decode($body, true) ?? [];
                    } catch (CanvasConnectionException $tokenException) {
                        // Token refresh failed and integration was deactivated
                        throw $tokenException;
                    } catch (\Exception $retryException) {
                        Log::error('Canvas API retry failed after token refresh', [
                            'integration_id' => $this->integration->id,
                            'endpoint' => $endpoint,
                            'error' => $retryException->getMessage(),
                        ]);
                        throw $retryException;
                    }
                }

                Log::error('Canvas API request failed', [
                    'integration_id' => $this->integration->id,
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);

                throw $e;
            }
        }

        // Should not reach here, but just in case
        if ($lastException) {
            throw $lastException;
        }

        return [];
    }

    /**
     * Get paginated results from Canvas API
     */
    public function getPaginated(string $endpoint, array $params = [], int $perPage = 100): array
    {
        $params['per_page'] = $perPage;
        $allResults = [];
        $page = 1;
        $startTime = microtime(true);

        Log::info('Starting pagination', [
            'endpoint' => $endpoint,
            'per_page' => $perPage,
        ]);

        do {
            $pageStartTime = microtime(true);
            $params['page'] = $page;
            
            Log::info('Fetching page', [
                'page' => $page,
                'endpoint' => $endpoint,
            ]);
            
            $results = $this->get($endpoint, $params);
            
            $pageTime = microtime(true) - $pageStartTime;
            
            Log::info('Page fetched', [
                'page' => $page,
                'results_count' => count($results),
                'page_time_seconds' => round($pageTime, 2),
                'total_so_far' => count($allResults) + count($results),
            ]);
            
            if (empty($results)) {
                Log::info('Empty results, stopping pagination', ['page' => $page]);
                break;
            }

            $allResults = array_merge($allResults, $results);
            $page++;

            // Safety limit to prevent infinite loops
            if ($page > 1000) {
                Log::warning('Canvas API pagination exceeded 1000 pages', [
                    'endpoint' => $endpoint,
                    'integration_id' => $this->integration->id,
                ]);
                break;
            }
        } while (count($results) === $perPage);

        $totalTime = microtime(true) - $startTime;
        
        Log::info('Pagination completed', [
            'endpoint' => $endpoint,
            'total_pages' => $page - 1,
            'total_results' => count($allResults),
            'total_time_seconds' => round($totalTime, 2),
        ]);

        return $allResults;
    }
}
