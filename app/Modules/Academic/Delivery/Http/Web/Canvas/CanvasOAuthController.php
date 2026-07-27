<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Models\CanvasIntegration;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasApiService;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CanvasOAuthController extends Controller
{
    public function __construct(
        private CanvasApiService $apiService,
        private CanvasTokenService $tokenService
    ) {}

    public function redirect(CanvasIntegration $integration): RedirectResponse
    {
        $state = Str::random(40);
        session()->put('canvas_oauth_state', $state);
        session()->put('canvas_integration_id', $integration->id);

        $redirectUri = route('admin.canvas.oauth.callback');
        $authUrl = $this->apiService->getAuthorizationUrl($integration, $redirectUri, $state);

        return redirect($authUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        \Log::info('Canvas OAuth Callback', [
            'request_data' => $request->all(),
            'session_state' => session('canvas_oauth_state'),
            'session_integration_id' => session('canvas_integration_id'),
        ]);

        // Verify state to prevent CSRF
        $state = session()->pull('canvas_oauth_state');
        $integrationId = session()->pull('canvas_integration_id');

        \Log::info('Canvas OAuth State Check', [
            'session_state' => $state,
            'request_state' => $request->state,
            'states_match' => $state === $request->state,
            'integration_id' => $integrationId,
        ]);

        if (! $state || $state !== $request->state) {
            \Log::error('Canvas OAuth State Mismatch');
            return redirect()
                ->route('admin.canvas.integrations.index')
                ->withErrors(['error' => 'Invalid OAuth state. Please try again.']);
        }

        if ($request->has('error')) {
            \Log::error('Canvas OAuth Error', [
                'error' => $request->error,
                'error_description' => $request->error_description,
            ]);
            return redirect()
                ->route('admin.canvas.integrations.index')
                ->withErrors(['error' => 'Authorization failed: ' . ($request->error_description ?? $request->error)]);
        }

        if (! $request->has('code')) {
            \Log::error('Canvas OAuth No Code');
            return redirect()
                ->route('admin.canvas.integrations.index')
                ->withErrors(['error' => 'No authorization code received']);
        }

        $integration = CanvasIntegration::find($integrationId);

        if (! $integration) {
            \Log::error('Canvas Integration Not Found', ['integration_id' => $integrationId]);
            return redirect()
                ->route('admin.canvas.integrations.index')
                ->withErrors(['error' => 'Integration not found']);
        }

        \Log::info('Canvas Integration Found', [
            'integration_id' => $integration->id,
            'canvas_url' => $integration->canvas_url,
            'client_id' => $integration->client_id,
        ]);

        try {
            $redirectUri = route('admin.canvas.oauth.callback');
            \Log::info('Exchanging Code for Token', [
                'redirect_uri' => $redirectUri,
                'code' => substr($request->code, 0, 10) . '...',
            ]);

            $success = $this->tokenService->exchangeCodeForToken(
                $integration,
                $request->code,
                $redirectUri
            );

            \Log::info('Token Exchange Result', ['success' => $success]);

            if (! $success) {
                \Log::error('Token Exchange Failed');
                return redirect()
                    ->route('admin.canvas.integrations.index')
                    ->withErrors(['error' => 'Failed to exchange authorization code for token. Check logs for details.']);
            }

            \Log::info('Canvas Authorization Successful');
            return redirect()
                ->route('admin.canvas.integrations.index')
                ->with('success', 'Canvas authorization successful! You can now sync courses.');
        } catch (\Exception $e) {
            \Log::error('Canvas OAuth Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()
                ->route('admin.canvas.integrations.index')
                ->withErrors(['error' => 'Authorization failed: ' . $e->getMessage()]);
        }
    }

    public function revoke(CanvasIntegration $integration): RedirectResponse
    {
        try {
            $this->tokenService->revokeToken($integration);

            return back()->with('success', 'Canvas authorization revoked successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to revoke authorization: ' . $e->getMessage()]);
        }
    }
}
