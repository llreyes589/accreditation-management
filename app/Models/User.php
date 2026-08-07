<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Institutions this user belongs to.
     *
     * @return BelongsToMany<Institution, $this>
     */
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Roles held by this user, optionally scoped to an institution.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('institution_id')
            ->withTimestamps();
    }

    /**
     * Ids of every institution this user is a member of.
     *
     * @return Collection<int, int>
     */
    public function institutionIds(): Collection
    {
        return $this->institutions->pluck('id');
    }

    /**
     * The user's primary institution, if one is flagged; otherwise the first.
     */
    public function primaryInstitution(): ?Institution
    {
        return $this->institutions
            ->sortByDesc(fn (Institution $i): bool => (bool) $i->getAttribute('pivot')->is_primary)
            ->first();
    }

    /**
     * Whether the user holds the given role, optionally within an institution.
     *
     * A role assignment with a null institution_id is global and satisfies any
     * institution-scoped check.
     */
    public function hasRole(RoleName|string $role, ?int $institutionId = null): bool
    {
        $name = $role instanceof RoleName ? $role->value : $role;

        return $this->roles->contains(function (Role $candidate) use ($name, $institutionId): bool {
            if ($candidate->name !== $name) {
                return false;
            }

            $scope = $candidate->getAttribute('pivot')->institution_id;

            return $scope === null
                || $institutionId === null
                || (int) $scope === $institutionId;
        });
    }

    /**
     * Whether the user holds any of the given roles.
     *
     * @param  array<int, RoleName|string>  $roles
     */
    public function hasAnyRole(array $roles, ?int $institutionId = null): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role, $institutionId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * PSP administrators hold the system-wide configuration remit.
     */
    public function isPspAdministrator(): bool
    {
        return $this->hasRole(RoleName::PspAdministrator);
    }

    /**
     * Whether the user's remit spans institutions (PSP, CART, inspector, auditor).
     */
    public function hasCrossInstitutionRole(): bool
    {
        return $this->hasAnyRole(RoleName::crossInstitutionRoles());
    }

    /**
     * Whether the user is a member of the given institution.
     */
    public function belongsToInstitution(int $institutionId): bool
    {
        return $this->institutions->contains('id', $institutionId);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
