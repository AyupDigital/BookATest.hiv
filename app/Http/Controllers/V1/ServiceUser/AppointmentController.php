<?php

namespace App\Http\Controllers\V1\ServiceUser;

use App\Events\EndpointHit;
use App\Http\Requests\ServiceUser\Appointment\IndexRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\ServiceUser;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\Filter;
use Spatie\QueryBuilder\QueryBuilder;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\ServiceUser\Appointment\IndexRequest $request
     * @param  \App\Models\ServiceUser $serviceUser
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request, ServiceUser $serviceUser)
    {
        // Prepare the base query.
        $baseQuery = Appointment::query()
            ->where('service_user_id', $serviceUser->id);

        // Specify allowed modifications to the query via the GET parameters.
        $appointments = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                Filter::exact('id'),
                Filter::exact('user_id'),
                Filter::exact('clinic_id'),
                Filter::scope('available')
            )
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate();

        event(EndpointHit::onRead($request, "Listed appointments for service user [$serviceUser->id]"));

        return AppointmentResource::collection($appointments);
    }
}