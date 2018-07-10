<?php

namespace App\Models;

use App\Models\Mutators\UserMutators;
use App\Models\Relationships\UserRelationships;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;
    use UserMutators;
    use UserRelationships;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'display_email',
        'display_phone',
        'include_calendar_attachment',
        'calendar_feed_token',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'disabled_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'display_phone' => 'boolean',
        'display_email' => 'boolean',
        'include_calendar_attachment' => 'boolean',
    ];

    /**
     * @param \App\Models\Role $role
     * @param \App\Models\Clinic|null $clinic
     * @return bool
     */
    protected function hasRole(Role $role, Clinic $clinic = null): bool
    {
        $query = $this->userRoles()->where('user_roles.role_id', $role->id);

        return $clinic
            ? $query->where('user_roles.clinic_id', $clinic->id)->exists()
            : $query->exists();
    }

    /**
     * @param \App\Models\Role $role
     * @param \App\Models\Clinic|null $clinic
     * @return \App\Models\User
     */
    protected function assignRole(Role $role, Clinic $clinic = null): self
    {
        // Check if the user already has the role.
        if ($this->hasRole($role)) {
            return $this;
        }

        // Create the role.
        UserRole::create(array_filter([
            'user_id' => $this->id,
            'role_id' => $role->id,
            'clinic_id' => $clinic->id ?? null,
        ]));

        return $this;
    }

    /**
     * @param \App\Models\Role $role
     * @param \App\Models\Clinic|null $clinic
     * @return \App\Models\User
     */
    protected function removeRoll(Role $role, Clinic $clinic = null): self
    {
        // Check if the user doesn't already have the role.
        if (!$this->hasRole($role)) {
            return $this;
        }

        // Remove the role.
        $this->userRoles()->where('role_id', $role->id)->delete();

        return $this;
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return \App\Models\User
     */
    public function makeCommunityWorker(Clinic $clinic): self
    {
        return $this->assignRole(Role::communityWorker(), $clinic);
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return \App\Models\User
     */
    public function makeClinicAdmin(Clinic $clinic): self
    {
        $this->assignRole(Role::communityWorker(), $clinic);
        $this->assignRole(Role::clinicAdmin(), $clinic);

        return $this;
    }

    /**
     * @return \App\Models\User
     */
    public function makeOrganisationAdmin(): self
    {
        Clinic::all()->each(function (Clinic $clinic) {
            $this->assignRole(Role::communityWorker(), $clinic);
            $this->assignRole(Role::clinicAdmin(), $clinic);
        });

        $this->assignRole(Role::organisationAdmin());

        return $this;
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return \App\Models\User
     * @throws \Exception
     */
    public function revokeCommunityWorker(Clinic $clinic): self
    {
        $clinicAdminRole = Role::clinicAdmin();

        if ($this->hasRole($clinicAdminRole, $clinic)) {
            throw new \Exception('Cannot revoke community worker role when user is a clinic admin');
        }

        return $this->removeRoll($clinicAdminRole, $clinic);
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return \App\Models\User
     * @throws \Exception
     */
    public function revokeClinicAdmin(Clinic $clinic): self
    {
        $this->removeRoll(Role::clinicAdmin(), $clinic);
        $this->removeRoll(Role::communityWorker(), $clinic);

        return $this;
    }

    /**
     * @return \App\Models\User
     * @throws \Exception
     */
    public function revokeOrganisationAdmin(): self
    {
        return $this->removeRoll(Role::organisationAdmin());
    }
}
