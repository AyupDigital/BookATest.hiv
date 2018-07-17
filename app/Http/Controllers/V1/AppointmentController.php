<?php

namespace App\Http\Controllers\V1;

use App\Events\EndpointHit;
use App\Http\Requests\Appointment\DestroyRequest;
use App\Http\Requests\Appointment\IndexRequest;
use App\Http\Requests\Appointment\ShowRequest;
use App\Http\Requests\Appointment\UpdateRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Responses\ResourceDeletedResponse;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * AppointmentController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\Appointment\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        event(EndpointHit::onRead($request, 'Viewed all appointments'));

        $appointments = Appointment::orderByDesc('created_at')->paginate();

        return AppointmentResource::collection($appointments);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Appointment\ShowRequest $request
     * @param  \App\Models\Appointment $appointment
     * @return \App\Http\Resources\AppointmentResource
     */
    public function show(ShowRequest $request, Appointment $appointment)
    {
        event(EndpointHit::onRead($request, "Viewed appointment [$appointment->id]"));

        return new AppointmentResource($appointment);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Appointment\UpdateRequest $request
     * @param  \App\Models\Appointment $appointment
     * @return \App\Http\Resources\AppointmentResource
     */
    public function update(UpdateRequest $request, Appointment $appointment)
    {
        event(EndpointHit::onUpdate($request, "Updated appointment [$appointment->id]"));

        $appointment = DB::transaction(function () use ($request, $appointment) {
            $appointment->did_not_attend = $request->input('did_not_attend');
            $appointment->save();

            return $appointment;
        });

        return new AppointmentResource($appointment);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\Appointment\DestroyRequest $request
     * @param  \App\Models\Appointment $appointment
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, Appointment $appointment)
    {
        event(EndpointHit::onDelete($request, "Deleted appointment [$appointment->id]"));

        return DB::transaction(function () use ($appointment) {
            $appointment->delete();

            return new ResourceDeletedResponse(Appointment::class);
        });
    }
}
