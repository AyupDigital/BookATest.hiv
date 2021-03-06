<?php

namespace App\Models;

use App\Models\Mutators\AppointmentScheduleMutators;
use App\Models\Relationships\AppointmentScheduleRelationships;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Date;

class AppointmentSchedule extends Model
{
    use AppointmentScheduleMutators;
    use AppointmentScheduleRelationships;
    use SoftDeletes;

    const MONDAY = 1;
    const TUESDAY = 2;
    const WEDNESDAY = 3;
    const THURSDAY = 4;
    const FRIDAY = 5;
    const SATURDAY = 6;
    const SUNDAY = 7;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @param int|null $daysToSkip
     * @param int $daysUpTo
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function createAppointments(int $daysToSkip = 60, int $daysUpTo = 90): Collection
    {
        $appointments = new Collection();

        // Loop through the date range.
        foreach (range($daysToSkip, $daysUpTo) as $day) {
            // Get the date of the looped day in the future.
            $startAt = Date::today()
                ->addDays($day)
                ->setTimeFromTimeString($this->weekly_at);

            /*
             * If the time is not the same, this indicates the BST has taken effect and PHP
             * has added an hour to compensate for the hour gap. Therefore this appointment
             * should be skipped.
             */
            if ($startAt->toTimeString() !== $this->weekly_at) {
                continue;
            }

            // Skip the day if it does not fall on the repeat day of week.
            if ($startAt->dayOfWeekIso !== $this->weekly_on) {
                continue;
            }

            $appointmentExists = Appointment::query()
                ->where('user_id', $this->user_id)
                ->where('clinic_id', $this->clinic_id)
                ->where('start_at', $startAt->timezone('UTC'))
                ->exists();

            // Don't create an appointment if one already exists.
            if ($appointmentExists) {
                continue;
            }

            // Create an appointment and append to the collection.
            $appointments->push(Appointment::create([
                'user_id' => $this->user_id,
                'clinic_id' => $this->clinic_id,
                'appointment_schedule_id' => $this->id,
                'start_at' => $startAt,
            ]));
        }

        return $appointments;
    }

    /**
     * @param \Carbon\CarbonInterface $date
     * @throws \Exception
     */
    public function deleteFrom(CarbonInterface $date)
    {
        $this->appointments()
            ->available()
            ->where('appointments.start_at', '>=', $date->timezone('UTC'))
            ->get()
            ->each
            ->delete();

        $this->delete();
    }
}
