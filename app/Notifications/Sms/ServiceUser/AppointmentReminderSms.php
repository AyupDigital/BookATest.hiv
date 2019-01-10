<?php

namespace App\Notifications\Sms\ServiceUser;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Setting;
use App\Notifications\Sms\Sms;

class AppointmentReminderSms extends Sms
{
    /**
     * AccessCodeSms constructor.
     *
     * @param \App\Models\Appointment $appointment
     */
    public function __construct(Appointment $appointment)
    {
        parent::__construct();

        $organisationName = Setting::getValue(Setting::NAME);

        $this->to = $appointment->serviceUser->phone;
        $this->message = "Reminder, you have an appointment tomorrow at {$appointment->start_at->format('l jS F H:i')} with {$organisationName}.";
        $this->notification = $appointment->serviceUser->notifications()->create([
            'channel' => Notification::SMS,
            'recipient' => $appointment->serviceUser->phone,
            'message' => $this->message,
        ]);
    }
}
