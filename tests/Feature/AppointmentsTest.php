<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSchedule;
use App\Models\Clinic;
use App\Models\ServiceUser;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AppointmentsTest extends TestCase
{
    /*
     * List them.
     */

    public function test_guest_cannot_view_all_appointments()
    {
        $response = $this->json('GET', '/v1/appointments');

        $response->assertStatus(401);
    }

    public function test_cw_can_view_all_appointments()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        Passport::actingAs($user);

        $response = $this->json('GET', '/v1/appointments');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $appointment->id,
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => null,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => null,
            'did_not_attend' => null,
        ]);
    }

    public function test_guest_cannot_view_appointment()
    {
        $user = factory(User::class)->create();
        $clinic = factory(Clinic::class)->create();
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        $response = $this->json('GET', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(401);
    }

    public function test_cw_can_view_all_appointments_for_a_user()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $anotherUser = factory(User::class)->create();
        $anotherUser->makeCommunityWorker($clinic);

        Passport::actingAs($anotherUser);

        $response = $this->json('GET', "/v1/users/{$ownerUser->id}/appointments");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $appointment->id,
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => null,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => null,
            'did_not_attend' => null,
        ]);
    }

    public function test_guest_cannot_view_all_appointments_for_a_user()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);

        $response = $this->json('GET', "/v1/users/{$user->id}/appointments");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_cw_can_view_all_appointments_for_a_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $bookedAt = today()->addDay()->setTime(12, 24);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $bookedAppointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $bookedAppointment->service_user_id = $serviceUser->id;
        $bookedAppointment->booked_at = $bookedAt;
        $bookedAppointment->save();

        Passport::actingAs($user);

        $response = $this->json('GET', "/v1/clinics/{$clinic->id}/appointments");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $appointment->id,
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => null,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => null,
            'did_not_attend' => null,
        ]);
        $response->assertJsonFragment([
            'id' => $bookedAppointment->id,
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => $serviceUser->id,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => $bookedAt->format(Carbon::ISO8601),
            'did_not_attend' => null,
        ]);
    }

    public function test_guest_can_view_all_available_appointments_for_a_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $bookedAppointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $bookedAppointment->service_user_id = $serviceUser->id;
        $bookedAppointment->save();

        $response = $this->json('GET', "/v1/clinics/{$clinic->id}/appointments");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $appointment->id,
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => null,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => null,
            'did_not_attend' => null,
        ]);
    }

    public function test_cw_can_view_all_appointments_for_a_su()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $bookedAt = $startAt->copy()->addHour();
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->booked_at = $bookedAt;
        $appointment->save();

        Passport::actingAs($user);

        $response = $this->json('GET', "/v1/service-users/{$serviceUser->id}/appointments");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'id' => $appointment->id,
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => $serviceUser->id,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => $bookedAt->format(Carbon::ISO8601),
            'did_not_attend' => null,
        ]);
    }

    public function test_guest_cannot_view_all_appointments_for_a_su()
    {
        $serviceUser = factory(ServiceUser::class)->create();

        $response = $this->json('GET', "/v1/service-users/{$serviceUser->id}/appointments");

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /*
     * Create one.
     */

    public function test_cw_can_create_appointment_at_their_own_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);

        Passport::actingAs($user);

        $response = $this->json('POST', "/v1/clinics/{$clinic->id}/appointments", [
            'start_at' => $startAt->format(Carbon::ISO8601),
            'is_repeating' => false,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => false,
            'service_user_id' => null,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => null,
            'did_not_attend' => null,
        ]);
    }

    public function test_cw_cannot_create_appointment_at_different_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $differentClinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);

        Passport::actingAs($user);

        $response = $this->json('POST', "/v1/clinics/{$differentClinic->id}/appointments", [
            'start_at' => $startAt->format(Carbon::ISO8601),
            'is_repeating' => false,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_guest_cannot_create_appointment_at_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $startAt = today()->addDay()->setTime(10, 30);

        $response = $this->json('POST', "/v1/clinics/{$clinic->id}/appointments", [
            'start_at' => $startAt->format(Carbon::ISO8601),
            'is_repeating' => false,
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_cw_can_create_repeating_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);

        Passport::actingAs($user);

        $response = $this->json('POST', "/v1/clinics/{$clinic->id}/appointments", [
            'start_at' => $startAt->format(Carbon::ISO8601),
            'is_repeating' => true,
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonFragment([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'is_repeating' => true,
            'service_user_id' => null,
            'start_at' => $startAt->format(Carbon::ISO8601),
            'booked_at' => null,
            'did_not_attend' => null,
        ]);
        $this->assertDatabaseHas('appointment_schedules', [
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'weekly_on' => $startAt->dayOfWeek,
            'weekly_at' => $startAt->toTimeString(),
        ]);
        foreach (range(0, 90) as $day) {
            // Get the date of the looped day in the future.
            $dateTime = $startAt->copy()
                ->addDays($day)
                ->setTime($startAt->hour, $startAt->minute);

            // Make sure no record was created if it does not fall on the repeat day of week.
            if ($dateTime->dayOfWeek !== $startAt->dayOfWeek) {
                $this->assertDatabaseMissing('appointments', [
                    'user_id' => $user->id,
                    'clinic_id' => $clinic->id,
                    'start_at' => $startAt->copy()->addDays($day)->toDateTimeString(),
                ]);

                continue;
            }

            $this->assertDatabaseHas('appointments', [
                'user_id' => $user->id,
                'clinic_id' => $clinic->id,
                'start_at' => $startAt->copy()->addDays($day)->toDateTimeString(),
            ]);
        }
    }

    public function test_cw_cannot_create_overlapping_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        Passport::actingAs($user);

        $response = $this->json('POST', "/v1/clinics/{$clinic->id}/appointments", [
            'start_at' => $startAt->format(Carbon::ISO8601),
            'is_repeating' => false,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_cw_cannot_create_appointment_outside_of_slot()
    {
        $clinic = factory(Clinic::class)->create(['appointment_duration' => 60]);
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);

        Passport::actingAs($user);

        $response = $this->json('POST', "/v1/clinics/{$clinic->id}/appointments", [
            'start_at' => $startAt->format(Carbon::ISO8601),
            'is_repeating' => false,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /*
     * Read one.
     */

    public function test_cw_can_view_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        Passport::actingAs($user);

        $response = $this->json('GET', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $appointment->id,
                'user_id' => $user->id,
                'clinic_id' => $clinic->id,
                'is_repeating' => false,
                'service_user_id' => null,
                'start_at' => $startAt->format(Carbon::ISO8601),
                'booked_at' => null,
                'did_not_attend' => null,
            ]
        ]);
    }

    /*
     * Update one.
     */

    public function test_guest_cannot_update_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}", [
            'did_not_attend' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_cw_cannot_update_someone_elses_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $differentUser = factory(User::class)->create();
        $differentUser->makeCommunityWorker($clinic);

        Passport::actingAs($differentUser);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}", [
            'did_not_attend' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_cw_can_update_their_own_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        Passport::actingAs($user);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}", [
            'did_not_attend' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $appointment->id,
                'user_id' => $user->id,
                'clinic_id' => $clinic->id,
                'is_repeating' => false,
                'service_user_id' => null,
                'start_at' => $startAt->format(Carbon::ISO8601),
                'booked_at' => null,
                'did_not_attend' => true,
            ]
        ]);
    }

    /*
     * Delete one.
     */

    public function test_guest_cannot_delete_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(401);
    }

    public function test_cw_can_delete_their_own_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $appointmentId = $appointment->id;

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'The Appointment has been successfully deleted']);
        $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
    }

    public function test_cw_can_delete_someone_elses_appointment_at_same_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $appointmentId = $appointment->id;
        $differentUser = factory(User::class)->create();
        $differentUser->makeCommunityWorker($clinic);

        Passport::actingAs($differentUser);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'The Appointment has been successfully deleted']);
        $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
    }

    public function test_cw_cannot_delete_someone_elses_appointment_at_different_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $differentClinic = factory(Clinic::class)->create();
        $differentUser = factory(User::class)->create();
        $differentUser->makeCommunityWorker($differentClinic);

        Passport::actingAs($differentUser);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(403);
    }

    public function test_cw_cannot_delete_a_booked_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $serviceUser = factory(ServiceUser::class)->create();
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_guest_cannot_delete_appointment_schedule()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $startAt = today()->addDay()->setTime(10, 30);
        $schedule = AppointmentSchedule::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'weekly_on' => $startAt->dayOfWeek,
            'weekly_at' => $startAt->toTimeString(),
        ]);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'appointment_schedule_id' => $schedule->id,
            'start_at' => $startAt,
        ]);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}/schedule");

        $response->assertStatus(401);
    }

    public function test_cw_cannot_delete_someone_elses_appointment_schedule()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $schedule = AppointmentSchedule::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'weekly_on' => $startAt->dayOfWeek,
            'weekly_at' => $startAt->toTimeString(),
        ]);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'appointment_schedule_id' => $schedule->id,
            'start_at' => $startAt,
        ]);
        $differentUser = factory(User::class)->create();
        $differentUser->makeCommunityWorker($clinic);

        Passport::actingAs($differentUser);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}/schedule");

        $response->assertStatus(403);
    }

    public function test_cw_can_delete_their_own_appointment_schedule()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $schedule = AppointmentSchedule::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'weekly_on' => $startAt->dayOfWeek,
            'weekly_at' => $startAt->toTimeString(),
        ]);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'appointment_schedule_id' => $schedule->id,
            'start_at' => $startAt,
        ]);
        $scheduleId = $schedule->id;
        $appointmentId = $appointment->id;

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/v1/appointments/{$appointment->id}/schedule");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('appointment_schedules', ['id' => $scheduleId, 'deleted_at' => null]);
        $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
    }

    public function test_cw_can_only_delete_unbooked_appointments_when_deleting_an_appointment_schedule()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $schedule = AppointmentSchedule::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'weekly_on' => $startAt->dayOfWeek,
            'weekly_at' => $startAt->toTimeString(),
        ]);
        $appointments = collect(range(0, 9))
            ->map(function (int $index) use ($user, $clinic, $schedule, $startAt) {
                return Appointment::create([
                    'user_id' => $user->id,
                    'clinic_id' => $clinic->id,
                    'appointment_schedule_id' => $schedule->id,
                    'start_at' => $startAt->addWeeks($index),
                ]);
            });
        $serviceUser = factory(ServiceUser::class)->create();
        $appointments[5]->service_user_id = $serviceUser->id;
        $appointments[5]->save();
        $scheduleId = $schedule->id;
        $appointmentIds = $appointments->map(function (Appointment $appointment) {
            return $appointment->id;
        });

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/v1/appointments/{$appointments->first()->id}/schedule");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('appointment_schedules', ['id' => $scheduleId, 'deleted_at' => null]);
        foreach ($appointmentIds as $index => $appointmentId) {
            if ($index === 5) {
                $this->assertDatabaseHas('appointments', ['id' => $appointmentId]);
            } else {
                $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
            }
        }
    }

    public function test_appointment_schedule_only_deletes_current_and_future_appointments()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $schedule = AppointmentSchedule::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'weekly_on' => $startAt->dayOfWeek,
            'weekly_at' => $startAt->toTimeString(),
        ]);
        $appointments = collect(range(0, 9))
            ->map(function (int $index) use ($user, $clinic, $schedule, $startAt) {
                return Appointment::create([
                    'user_id' => $user->id,
                    'clinic_id' => $clinic->id,
                    'appointment_schedule_id' => $schedule->id,
                    'start_at' => $startAt->addWeeks($index),
                ]);
            });
        $scheduleId = $schedule->id;
        $appointmentIds = $appointments->map(function (Appointment $appointment) {
            return $appointment->id;
        });

        Passport::actingAs($user);

        $response = $this->json('DELETE', "/v1/appointments/{$appointments[5]->id}/schedule");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('appointment_schedules', ['id' => $scheduleId, 'deleted_at' => null]);
        foreach ($appointmentIds as $index => $appointmentId) {
            if ($index >= 5) {
                $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
            } else {
                $this->assertDatabaseHas('appointments', ['id' => $appointmentId]);
            }
        }
    }

    /*
     * Cancel one.
     */

    public function test_cw_can_cancel_their_own_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();

        Passport::actingAs($user);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id, 'service_user_id' => $serviceUser->id]);
    }

    public function test_cw_can_cancel_someone_elses_appointment_at_same_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();
        $differentUser = factory(User::class)->create();
        $differentUser->makeCommunityWorker($clinic);

        Passport::actingAs($differentUser);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id, 'service_user_id' => $serviceUser->id]);
    }

    public function test_cw_cannot_cancel_someone_elses_appointment_at_different_clinic()
    {
        $clinic = factory(Clinic::class)->create();
        $ownerUser = factory(User::class)->create();
        $ownerUser->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $ownerUser->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();
        $differentClinic = factory(Clinic::class)->create();
        $differentUser = factory(User::class)->create();
        $differentUser->makeCommunityWorker($differentClinic);

        Passport::actingAs($differentUser);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'service_user_id' => $serviceUser->id]);
    }

    public function test_su_can_cancel_their_own_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}/cancel", [
            'service_user_token' => $serviceUser->generateToken(),
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id, 'service_user_id' => $serviceUser->id]);
    }

    public function test_su_cannot_cancel_someone_elses_appointment()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->addDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();
        $anotherServiceUser = factory(ServiceUser::class)->create();

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}/cancel", [
            'service_user_token' => $anotherServiceUser->generateToken(),
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'service_user_id' => $serviceUser->id]);
    }

    public function test_cw_cannot_cancel_their_own_appointment_in_the_past()
    {
        $clinic = factory(Clinic::class)->create();
        $user = factory(User::class)->create();
        $user->makeCommunityWorker($clinic);
        $startAt = today()->subDay()->setTime(10, 30);
        $appointment = Appointment::create([
            'user_id' => $user->id,
            'clinic_id' => $clinic->id,
            'start_at' => $startAt,
        ]);
        $serviceUser = factory(ServiceUser::class)->create();
        $appointment->service_user_id = $serviceUser->id;
        $appointment->save();

        Passport::actingAs($user);

        $response = $this->json('PUT', "/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'service_user_id' => $serviceUser->id]);
    }
}
