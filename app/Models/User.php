<?php

namespace App\Models;

use App\Models\Mutators\UserMutators;
use App\Models\Relationships\UserRelationships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;
use RuntimeException;

class User extends Authenticatable
{
    use HasApiTokens;
    use UserMutators;
    use UserRelationships;

    const EXCLUSIVE = true;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'display_phone' => 'boolean',
        'display_email' => 'boolean',
        'include_calendar_attachment' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = uuid();
            }
        });
    }

    /**
     * @param \Illuminate\Support\Carbon|null $dateTime
     * @return \App\Models\User
     */
    public function disable(Carbon $dateTime = null): self
    {
        $dateTime = $dateTime ?? now();
        $this->update(['disabled_at' => $dateTime]);

        return $this;
    }

    /**
     * @return \App\Models\User
     */
    public function enable(): self
    {
        $this->update(['disabled_at' => null]);

        return $this;
    }

    /**
     * @param \App\Models\Role $role
     * @param \App\Models\Clinic|null $clinic
     * @return bool
     */
    public function hasRole(Role $role, Clinic $clinic = null): bool
    {
        return $this->userRoles()
            ->where('user_roles.role_id', $role->id)
            ->when($clinic, function (Builder $query) use ($clinic): Builder {
                return $query->where('user_roles.clinic_id', $clinic->id);
            })
            ->exists();
    }

    /**
     * @param \App\Models\Role $role
     * @param \App\Models\Clinic|null $clinic
     * @return \App\Models\User
     */
    protected function assignRole(Role $role, Clinic $clinic = null): self
    {
        // Check if the user already has the role.
        if ($this->hasRole($role, $clinic)) {
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
    public function removeRoll(Role $role, Clinic $clinic = null): self
    {
        // Check if the user doesn't already have the role.
        if (!$this->hasRole($role, $clinic)) {
            return $this;
        }

        // Remove the role.
        $this
            ->userRoles()
            ->where('role_id', $role->id)
            ->when($clinic, function (Builder $query) use ($clinic) {
                return $query->where('clinic_id', $clinic->id);
            })
            ->delete();

        return $this;
    }

    /**
     * @param \App\Models\Role $role
     * @param \App\Models\Clinic|null $clinic
     * @return bool
     */
    public function canAssignRole(Role $role, Clinic $clinic = null): bool
    {
        switch ($role->name) {
            case Role::COMMUNITY_WORKER:
                return $this->isClinicAdmin($clinic);
            case Role::CLINIC_ADMIN:
                return $this->isClinicAdmin($clinic);
            case Role::ORGANISATION_ADMIN:
                return $this->isOrganisationAdmin();
        }
    }

    /**
     * @param \App\Models\User $subjectUser
     * @param \App\Models\UserRole $userRole
     * @return bool
     */
    public function canRevokeRole(User $subjectUser, UserRole $userRole): bool
    {
        // Always allow if the user is an organisation admin.
        if ($this->isOrganisationAdmin()) {
            return true;
        }

        /*
         * Different logic depending on the role - user must be a clinic admin
         * or community worker at this point.
         */
        switch ($userRole->role->name) {
            case Role::ORGANISATION_ADMIN:
                return false;
            case Role::CLINIC_ADMIN:
                return false;
            case Role::COMMUNITY_WORKER:
                $requesterIsClinicAdmin = $this->isClinicAdmin($userRole->clinic);
                $subjectIsClinicAdmin = $subjectUser->isClinicAdmin($userRole->clinic);
                return $requesterIsClinicAdmin && !$subjectIsClinicAdmin;
        }

        return false;
    }

    /**
     * @param \App\Models\Clinic|null $clinic
     * @param bool $exclusive
     * @return bool
     */
    public function isCommunityWorker(Clinic $clinic = null, bool $exclusive = false): bool
    {
        $hasRole = $this->hasRole(Role::communityWorker(), $clinic);
        $isOrganisationAdmin = $this->hasRole(Role::organisationAdmin());

        return $exclusive
            ? $hasRole
            : ($hasRole || $isOrganisationAdmin);
    }

    /**
     * @param \App\Models\Clinic|null $clinic
     * @param bool $exclusive
     * @return bool
     */
    public function isClinicAdmin(Clinic $clinic = null, bool $exclusive = false): bool
    {
        $hasRole = $this->hasRole(Role::clinicAdmin(), $clinic);
        $isOrganisationAdmin = $this->hasRole(Role::organisationAdmin());

        return $exclusive
            ? $hasRole
            : ($hasRole || $isOrganisationAdmin);
    }

    /**
     * @return bool
     */
    public function isOrganisationAdmin(): bool
    {
        return $this->hasRole(Role::organisationAdmin());
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
     */
    public function revokeCommunityWorker(Clinic $clinic): self
    {
        $this->revokeClinicAdmin($clinic);

        return $this->removeRoll(Role::communityWorker(), $clinic);
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return \App\Models\User
     */
    public function revokeClinicAdmin(Clinic $clinic): self
    {
        $this->revokeOrganisationAdmin();

        return $this->removeRoll(Role::clinicAdmin(), $clinic);
    }

    /**
     * @return \App\Models\User
     */
    public function revokeOrganisationAdmin(): self
    {
        return $this->removeRoll(Role::organisationAdmin());
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return bool
     */
    public function canMakeCommunityWorker(Clinic $clinic): bool
    {
        return $this->canAssignRole(Role::communityWorker(), $clinic);
    }

    /**
     * @param \App\Models\Clinic $clinic
     * @return bool
     */
    public function canMakeClinicAdmin(Clinic $clinic): bool
    {
        return $this->canAssignRole(Role::clinicAdmin(), $clinic);
    }

    /**
     * @return bool
     */
    public function canMakeOrganisationAdmin(): bool
    {
        return $this->canAssignRole(Role::organisationAdmin());
    }

    /**
     * @param int $attempts
     * @return string
     */
    public static function generateCalendarFeedToken(int $attempts = 0): string
    {
        // Prevent infinite loop.
        if ($attempts > 10) {
            throw new RuntimeException('Failed generating calendar feed token');
        }

        // Generate the token.
        $token = strtoupper(str_random(10));

        // Use recursion if the token has already been used.
        if (static::where('calendar_feed_token', $token)->exists()) {
            $token = static::generateCalendarFeedToken(++$attempts);
        }

        return $token;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $updatedRoles
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAssignedRoles(Collection $updatedRoles): Collection
    {
        $assignedRoles = new Collection();

        foreach ($updatedRoles as $updatedRole) {
            switch ($updatedRole->role->name) {
                case Role::COMMUNITY_WORKER:
                    if (!$this->isCommunityWorker($updatedRole->clinic, User::EXCLUSIVE)) {
                        $assignedRoles->push($updatedRole);
                    }
                    break;
                case Role::CLINIC_ADMIN:
                    if (!$this->isClinicAdmin($updatedRole->clinic, User::EXCLUSIVE)) {
                        $assignedRoles->push($updatedRole);
                    }
                    break;
                case Role::ORGANISATION_ADMIN:
                    if (!$this->isOrganisationAdmin()) {
                        $assignedRoles->push($updatedRole);
                    }
                    break;
            }
        }

        return $assignedRoles;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $updatedRoles
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRevokedRoles(Collection $updatedRoles): Collection
    {
        return $this->userRoles
            ->load('role')
            ->reject(function (UserRole $userRole) use ($updatedRoles) {
                // Loop through each of the new set of roles.
                foreach ($updatedRoles as $updatedRole) {
                    // If the updated roles contain the current existing role then return true as a match.
                    switch ($updatedRole->role->name) {
                        case Role::COMMUNITY_WORKER:
                            if ($userRole->isCommunityWorker($updatedRole->clinic)) {
                                return true;
                            }
                            break;
                        case Role::CLINIC_ADMIN:
                            if ($userRole->isClinicAdmin($updatedRole->clinic)) {
                                return true;
                            }
                            break;
                        case Role::ORGANISATION_ADMIN:
                            if ($userRole->isOrganisationAdmin()) {
                                return true;
                            }
                            break;
                    }
                }

                // If after looping, their are no matches, then return false.
                return false;
            });
    }

    /**
     * @return \Illuminate\Http\Response
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function placeholderProfilePicture(): Response
    {
        $content = Storage::disk('local')->get('placeholders/profile-picture.png');

        return response()->make($content, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "inline; filename=\"profile-picture.png\"",
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection|null $clinics
     * @return int
     */
    public function appointmentsThisWeek(Collection $clinics = null): int
    {
        return $this->appointments()
            ->when($clinics, function (Builder $query) use ($clinics): Builder {
                return $query->whereIn('appointments.clinic_id', $clinics->pluck('id')->toArray());
            })
            ->thisWeek()
            ->count();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection|null $clinics
     * @return int
     */
    public function appointmentsAvailable(Collection $clinics = null): int
    {
        return $this->appointments()
            ->when($clinics, function (Builder $query) use ($clinics): Builder {
                return $query->whereIn('appointments.clinic_id', $clinics->pluck('id')->toArray());
            })
            ->thisWeek()
            ->available()
            ->count();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection|null $clinics
     * @return int
     */
    public function appointmentsBooked(Collection $clinics = null): int
    {
        return $this->appointments()
            ->when($clinics, function (Builder $query) use ($clinics): Builder {
                return $query->whereIn('appointments.clinic_id', $clinics->pluck('id')->toArray());
            })
            ->thisWeek()
            ->booked()
            ->count();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection|null $clinics
     * @return float|null
     */
    public function attendanceRateThisWeek(Collection $clinics = null): ?float
    {
        $appointmentsAttended = $this->appointments()
            ->when($clinics, function (Builder $query) use ($clinics): Builder {
                return $query->whereIn('appointments.clinic_id', $clinics->pluck('id')->toArray());
            })
            ->thisWeek()
            ->where('appointments.did_not_attend', '=', false)
            ->count();

        if ($appointmentsAttended === 0) {
            return null;
        }

        return ($appointmentsAttended / $this->appointmentsThisWeek($clinics)) * 100;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection|null $clinics
     * @return float|null
     */
    public function didNotAttendRateThisWeek(Collection $clinics = null): ?float
    {
        $appointmentsNotAttended = $this->appointments()
            ->when($clinics, function (Builder $query) use ($clinics): Builder {
                return $query->whereIn('appointments.clinic_id', $clinics->pluck('id')->toArray());
            })
            ->thisWeek()
            ->where('appointments.did_not_attend', '=', true)
            ->count();

        if ($appointmentsNotAttended === 0) {
            return null;
        }

        return ($appointmentsNotAttended / $this->appointmentsThisWeek($clinics)) * 100;
    }
}
