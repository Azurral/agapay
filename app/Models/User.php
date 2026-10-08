<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
    use Auditable, HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected string $auditSubject = 'User';

    /** Changes that happen on every sign-in are not user activity worth auditing. */
    protected array $auditIgnore = ['remember_token', 'last_login_at'];

    public function auditRecordLabel(): string
    {
        return $this->username;
    }

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

    /** "Maria Santos" → "MS", "Juan dela Cruz" → "JC", "Encoder" → "EN"; the username when there is no name. */
    public function initials(): string
    {
        $name = trim((string) $this->name);
        $words = $name === '' ? [(string) $this->username ?: '?'] : preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $initials = count($words) === 1
            ? mb_substr($words[0], 0, 2)
            : mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1);

        return mb_strtoupper($initials);
    }

    /** Avatar circle colour by role: purple Administrator, blue Agricultural Technologist, cyan Data Encoder. */
    public function avatarColor(): string
    {
        return match ($this->role?->slug) {
            Role::ADMIN => '#8037ff',
            Role::AGRITECH => '#4671ff',
            Role::ENCODER => '#0bcaff',
            default => '#9ca3af',
        };
    }
}
