<?php

namespace App\Http\Controllers\V1;

use App\Events\EndpointHit;
use App\Exceptions\CannotRevokeRoleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\DestroyRequest;
use App\Http\Requests\User\IndexRequest;
use App\Http\Requests\User\ShowRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdateRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ResourceDeletedResponse;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Filter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    /**
     * UserController constructor.
     */
    public function __construct()
    {
        $this->middleware('throttle:60,1');
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\User\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        // Prepare the base query.
        $baseQuery = User::query();

        // Specify allowed modifications to the query via the GET parameters.
        $users = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                Filter::exact('id'),
                Filter::scope('clinic_id'),
                Filter::scope('disabled')
            )
            ->defaultSort('first_name')
            ->allowedSorts('first_name', 'last_name')
            ->paginate(per_page($request->per_page));

        event(EndpointHit::onRead($request, 'Listed all users'));

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\User\StoreRequest $request
     * @return \App\Http\Resources\UserResource
     */
    public function store(StoreRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            // Create the user.
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'display_email' => $request->display_email,
                'display_phone' => $request->display_phone,
                'receive_booking_confirmations' => $request->receive_booking_confirmations,
                'receive_cancellation_confirmations' => $request->receive_cancellation_confirmations,
                'include_calendar_attachment' => $request->include_calendar_attachment,
                'calendar_feed_token' => User::generateCalendarFeedToken(),
            ]);

            // Create the roles.
            $userRoles = UserRole::parseArray($request->roles);
            foreach ($userRoles as $userRole) {
                switch ($userRole->role->name) {
                    case Role::COMMUNITY_WORKER:
                        $user->makeCommunityWorker($userRole->clinic);
                        break;
                    case Role::CLINIC_ADMIN:
                        $user->makeClinicAdmin($userRole->clinic);
                        break;
                    case Role::ORGANISATION_ADMIN:
                        $user->makeOrganisationAdmin();
                        break;
                }
            }

            // Upload the profile picture.
            if ($request->has('profile_picture')) {
                $profilePictureContent = base64_decode_image($request->profile_picture);
                $user->uploadProfilePicture($profilePictureContent);
            }

            return $user;
        });

        event(EndpointHit::onCreate($request, "Created user [{$user->id}]"));

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\User\ShowRequest $request
     * @param \App\Models\User $user
     * @return \App\Http\Resources\UserResource
     */
    public function show(ShowRequest $request, User $user)
    {
        event(EndpointHit::onRead($request, "Viewed user [{$user->id}]"));

        return new UserResource($user);
    }

    /**
     * @param \App\Http\Requests\User\ShowRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(ShowRequest $request)
    {
        return $this->show($request, $request->user('api'))
            ->toResponse($request)
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\User\UpdateRequest $request
     * @param \App\Models\User $user
     * @return \App\Http\Resources\UserResource
     */
    public function update(UpdateRequest $request, User $user)
    {
        $user = DB::transaction(function () use ($request, $user) {
            // Update the user.
            $user->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'display_email' => $request->display_email,
                'display_phone' => $request->display_phone,
                'receive_booking_confirmations' => $request->receive_booking_confirmations,
                'receive_cancellation_confirmations' => $request->receive_cancellation_confirmations,
                'include_calendar_attachment' => $request->include_calendar_attachment,
            ]);

            // If a password was given with the request, then update the password.
            if ($request->has('password')) {
                $user->update(['password' => bcrypt($request->password)]);
            }

            // Update the user roles.
            $userRoles = UserRole::parseArray($request->roles);
            $revokedRoles = $user->getRevokedRoles($userRoles);
            $newRoles = $user->getAssignedRoles($userRoles);

            // Revoke the deleted roles.
            foreach ($revokedRoles as $revokedRole) {
                try {
                    switch ($revokedRole->role->name) {
                        case Role::ORGANISATION_ADMIN:
                            $user->revokeOrganisationAdmin();
                            break;
                        case Role::CLINIC_ADMIN:
                            $user->revokeClinicAdmin($revokedRole->clinic);
                            break;
                        case Role::COMMUNITY_WORKER:
                            $user->revokeCommunityWorker($revokedRole->clinic);
                            break;
                    }
                } catch (CannotRevokeRoleException $exception) {
                    continue;
                }
            }

            // Assign the new roles.
            foreach ($newRoles as $newRole) {
                switch ($newRole->role->name) {
                    case Role::COMMUNITY_WORKER:
                        $user->makeCommunityWorker($newRole->clinic);
                        break;
                    case Role::CLINIC_ADMIN:
                        $user->makeClinicAdmin($newRole->clinic);
                        break;
                    case Role::ORGANISATION_ADMIN:
                        $user->makeOrganisationAdmin();
                        break;
                }
            }

            if ($request->filled('profile_picture')) {
                // Upload the profile picture.
                $profilePictureContent = base64_decode_image($request->profile_picture);
                $user->uploadProfilePicture($profilePictureContent);
            } elseif ($request->has('profile_picture')) {
                // Remove the profile picture.
                $user->removeProfilePicture();
            }

            return $user;
        });

        event(EndpointHit::onUpdate($request, "Updated user [{$user->id}]"));

        return new UserResource($user->fresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\User\DestroyRequest $request
     * @param \App\Models\User $user
     * @return \App\Http\Responses\ResourceDeletedResponse
     */
    public function destroy(DestroyRequest $request, User $user)
    {
        $userId = $user->id;

        DB::transaction(function () use ($user) {
            // Cancel all future booked appointments.
            $user->appointments()
                ->booked()
                ->future()
                ->chunk(200, function (Collection $appointments) {
                    $appointments->each->cancel();
                });

            // Delete all future appointments.
            $user->appointments()->future()->delete();

            // Delete the user.
            $user->disable();
        });

        event(EndpointHit::onDelete($request, "Disabled user [$userId]"));

        return new ResourceDeletedResponse(User::class);
    }
}
