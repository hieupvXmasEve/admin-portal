<?php

namespace App\Modules\Identity;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class IdentityContext
{
    private ?User $user = null;
    private ?int $campusId = null;
    private Collection $roles;
    private ?int $departmentId = null; 
    private bool $initialized = false;
    
    public function __construct()
    {
        $this->roles = collect();
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->init();
        }
    }
     /**
     * Initialize the context from the current request/session state.
     * This is typically called by the ServiceProvider.
     */
    public function init(): void
    {
        $this->user = Auth::user();
        
        if ($this->user) {
            $this->campusId = Session::get('current_campus_id');
            
            // Populate roles if campus is selected
            if ($this->campusId) {
                // We use the existing logic from User model
                // But typically IdentityContext should be the source of truth
                // For now, we proxy to valid existing logic or load from session if cached
                // Use a fresh query or relationship to ensure accuracy
                $this->roles = $this->user->campusRoles()
                    ->where('campus_id', $this->campusId)
                    ->get();
            }
        }
        
        $this->initialized = true;
    }

    public function user(): ?User
    {
        $this->ensureInitialized();
        return $this->user;
    }

    public function campusId(): ?int
    {
        $this->ensureInitialized();
        return $this->campusId;
    }

    public function roles(): Collection
    {
        $this->ensureInitialized();
        return $this->roles;
    }

    public function hasRole(string $roleCode): bool
    {
        $this->ensureInitialized();
        return $this->roles->contains('code', $roleCode);
    }

    public function isStaff(): bool
    {
        $this->ensureInitialized();
        return $this->user?->isStaff() ?? false;
    }

    public function isStudent(): bool
    {
        $this->ensureInitialized();
        return $this->user?->isStudent() ?? false;
    }

    public function isLecturer(): bool
    {
        $this->ensureInitialized();
        return $this->user?->isLecturer() ?? false;
    }

    public function isParent(): bool
    {
        $this->ensureInitialized();
        return $this->user?->isParent() ?? false;
    }
    
    public function check(): bool
    {
        $this->ensureInitialized();
        return $this->user !== null;
    }
}
