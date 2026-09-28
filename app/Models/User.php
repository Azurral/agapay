<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'username', 'email', 'password', 'role_id', 'status', 'avatar', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    private ?Collection $permissionSlugCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Active accounts with an assigned role are the only ones allowed in. */
    public function canSignIn(): bool
    {
        return $this->isActive() && $this->role_id !== null;
    }

    public function hasPermission(string $slug): bool
    {
        return $this->canSignIn() && $this->permissionSlugs()->contains($slug);
    }

    /** @return Collection<int, string> */
    public function permissionSlugs(): Collection
    {
        return $this->permissionSlugCache ??= ($this->role?->permissions->pluck('slug') ?? collect());
    }

    public function roleName(): string
    {
        return $this->role?->name ?? 'No Role';
    }

    public function roleShortName(): string
    {
        return $this->role?->short_name ?? 'No Role';
    }

    public function greetingName(): string
    {
        return $this->role?->greeting ?? $this->username;
    }

    public function avatarUrl(): string
    {
        $key = $this->avatar ?? match ($this->role?->slug) {
            Role::AGRITECH => 'agritech',
            Role::ENCODER => 'encoder',
            default => 'admin',
        };

        return asset("images/figma/avatars/{$key}.png");
    }
}
