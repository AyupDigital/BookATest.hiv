<?php

namespace App\Http\Controllers\V1;

use App\Events\EndpointHit;
use App\Http\Requests\Clinic\DestroyRequest;
use App\Http\Requests\Clinic\IndexRequest;
use App\Http\Requests\Clinic\ShowRequest;
use App\Http\Requests\Clinic\StoreRequest;
use App\Http\Requests\Clinic\UpdateRequest;
use App\Http\Resources\ClinicResource;
use App\Http\Responses\ResourceDeletedResponse;
use App\Models\Clinic;
use App\Models\Setting;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ClinicController extends Controller
{
    /**
     * ClinicController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->only('store', 'update', 'destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\Clinic\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        event(EndpointHit::onRead($request, 'Viewed all clinics'));

        $clinics = Clinic::orderByDesc('created_at')->paginate();

        return ClinicResource::collection($clinics);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Clinic\StoreRequest $request
     * @return \Illuminate\Http\Resources\Json\JsonResource
     */
    public function store(StoreRequest $request)
    {
        event(EndpointHit::onCreate($request, 'Created clinic'));

        return DB::transaction(function () use ($request) {
            $clinic = Clinic::create([
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'address_line_1' => $request->input('address_line_1'),
                'address_line_2' => $request->input('address_line_2'),
                'address_line_3' => $request->input('address_line_3'),
                'city' => $request->input('city'),
                'postcode' => $request->input('postcode'),
                'directions' => $request->input('directions'),
                'appointment_duration' => $request->input('appointment_duration', Setting::getValue(Setting::DEFAULT_APPOINTMENT_DURATION)),
                'appointment_booking_threshold' => $request->input('appointment_booking_threshold', Setting::getValue(Setting::DEFAULT_APPOINTMENT_BOOKING_THRESHOLD)),
            ]);

            return new ClinicResource($clinic);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Clinic\ShowRequest $request
     * @param  \App\Models\Clinic $clinic
     * @return \App\Http\Resources\ClinicResource
     */
    public function show(ShowRequest $request, Clinic $clinic)
    {
        event(EndpointHit::onCreate($request, "Viewed clinic [$clinic->id]"));

        return new ClinicResource($clinic);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\Clinic\UpdateRequest $request
     * @param  \App\Models\Clinic $clinic
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Clinic $clinic)
    {
        event(EndpointHit::onUpdate($request, "Updated clinic [$clinic->id]"));

        return DB::transaction(function () use ($request, $clinic) {
            $clinic->update([
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'email' => $request->input('email'),
                'address_line_1' => $request->input('address_line_1'),
                'address_line_2' => $request->input('address_line_2'),
                'address_line_3' => $request->input('address_line_3'),
                'city' => $request->input('city'),
                'postcode' => $request->input('postcode'),
                'directions' => $request->input('directions'),
                'appointment_duration' => $request->input('appointment_duration', Setting::getValue(Setting::DEFAULT_APPOINTMENT_DURATION)),
                'appointment_booking_threshold' => $request->input('appointment_booking_threshold', Setting::getValue(Setting::DEFAULT_APPOINTMENT_BOOKING_THRESHOLD)),
            ]);

            return new ClinicResource($clinic);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Http\Requests\Clinic\DestroyRequest $request
     * @param  \App\Models\Clinic $clinic
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyRequest $request, Clinic $clinic)
    {
        event(EndpointHit::onDelete($request, "Deleted clinic [$clinic->id]"));

        return DB::transaction(function () use ($clinic) {
            $clinic->delete();

            return new ResourceDeletedResponse(Clinic::class);
        });
    }
}
