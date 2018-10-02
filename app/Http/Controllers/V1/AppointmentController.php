<?php

namespace App\Http\Controllers\V1;

use App\Events\EndpointHit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\{IndexRequest, StoreRequest};
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\AppointmentSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Spatie\QueryBuilder\Filter;
use Spatie\QueryBuilder\QueryBuilder;

class AppointmentController extends Controller
{
    /**
     * AppointmentController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api')->except('index', 'show');
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Http\Requests\Appointment\IndexRequest $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(IndexRequest $request)
    {
        // Prepare the base query.
        $baseQuery = Appointment::query();

        // If a guest made the request, then limit to only available appointments.
        if (Auth::guest()) {
            $baseQuery = $baseQuery->available();
        }

        // Specify allowed modifications to the query via the GET parameters.
        $appointments = QueryBuilder::for($baseQuery)
            ->allowedFilters(
                Filter::exact('user_id'),
                Filter::exact('clinic_id'),
                Filter::exact('service_user_id'),
                Filter::scope('available')
            )
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate();

        event(EndpointHit::onRead($request, 'Listed all appointments'));

        return AppointmentResource::collection($appointments);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\Appointment\StoreRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request)
    {
        $appointment = DB::transaction(function () use ($request) {
            $startAt = Carbon::createFromFormat(Carbon::ISO8601, $request->start_at)
                ->second(0);

            // For repeating appointments.
            if ($request->is_repeating) {
                /** @var \App\Models\AppointmentSchedule $appointmentSchedule */
                $appointmentSchedule = AppointmentSchedule::create([
                    'user_id' => $request->user()->id,
                    'clinic_id' => $request->clinic_id,
                    'weekly_on' => $startAt->dayOfWeek,
                    'weekly_at' => $startAt->toTimeString(),
                ]);

                $daysToSkip = 0;
                $appointments = $appointmentSchedule->createAppointments($daysToSkip);

                return $appointments->first();
            }

            // For a single appointments.
            return Appointment::create([
                'user_id' => $request->user()->id,
                'clinic_id' => $request->clinic_id,
                'start_at' => $startAt,
            ]);
        });

        event(EndpointHit::onCreate($request, "Created appointment [{$appointment->id}]"));

        return (new AppointmentResource($appointment->fresh()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Appointment $appointment
     * @return \Illuminate\Http\Response
     */
    public function show(Appointment $appointment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Appointment $appointment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Appointment $appointment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Appointment $appointment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Appointment $appointment)
    {
        //
    }
}
