<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Http\Requests\CanvasIntegrationRequest;
use App\Models\CanvasIntegration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CanvasIntegrationController extends Controller
{
    public function index(): Response
    {
        // All campuses share one Canvas instance
        $integrations = CanvasIntegration::with(['createdBy', 'updatedBy'])
            ->latest()
            ->get()
            ->map(function ($integration) {
                return [
                    'id' => $integration->id,
                    'canvas_url' => $integration->canvas_url,
                    'client_id' => $integration->client_id,
                    'has_token' => ! is_null($integration->access_token),
                    'has_refresh_token' => ! is_null($integration->refresh_token),
                    'token_expires_at' => $integration->token_expires_at?->toIso8601String(),
                    'is_active' => $integration->is_active,
                    'sync_status' => $integration->sync_status,
                    'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
                    'sync_error' => $integration->sync_error,
                    'created_at' => $integration->created_at->toIso8601String(),
                    'created_by' => $integration->createdBy ? [
                        'id' => $integration->createdBy->id,
                        'name' => $integration->createdBy->name,
                    ] : null,
                ];
            });

        return Inertia::render('Admin/Canvas/Integrations/Index', [
            'integrations' => $integrations,
        ]);
    }

    public function store(CanvasIntegrationRequest $request): RedirectResponse
    {
        try {
            $integration = CanvasIntegration::create([
                'canvas_url' => rtrim($request->canvas_url, '/'),
                'client_id' => $request->client_id,
                'client_secret' => $request->client_secret,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            return redirect()
                ->route('admin.canvas.integrations.index')
                ->with('success', 'Canvas integration created successfully. Please authorize to start syncing.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create Canvas integration: '.$e->getMessage()]);
        }
    }

    public function destroy(CanvasIntegration $integration): RedirectResponse
    {
        try {
            $integration->delete();

            return redirect()
                ->route('admin.canvas.integrations.index')
                ->with('success', 'Canvas integration deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete Canvas integration: '.$e->getMessage()]);
        }
    }

    public function toggleActive(CanvasIntegration $integration): RedirectResponse
    {
        try {
            $integration->update([
                'is_active' => ! $integration->is_active,
                'updated_by' => auth()->id(),
            ]);

            $status = $integration->is_active ? 'activated' : 'deactivated';

            return back()->with('success', "Canvas integration {$status} successfully");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update Canvas integration: '.$e->getMessage()]);
        }
    }
}
