<?php

namespace App\Notifications\Email\CommunityWorker;

use App\Models\Appointment;
use App\Models\Notification;
use App\Notifications\Email\Email;

class BookingConfirmedEmail extends Email
{
    /**
     * BookingConfirmedEmail constructor.
     *
     * @param \App\Models\Appointment $appointment
     */
    public function __construct(Appointment $appointment)
    {
        parent::__construct();

        $phone = $appointment->serviceUser->phone ?? '(not provided)';

        $this->to = $appointment->user->email;
        $this->subject = 'Booking Confirmation';
        $this->message = <<<EOT
An appointment has been booked with {$appointment->clinic->name} at {$appointment->start_at->format('H:i')} 
by {$appointment->serviceUser->name}.

You can contact them by email on {$appointment->serviceUser->email} and by phone {$phone}.
EOT;
        $this->notification = $appointment->user->notifications()->create([
            'channel' => Notification::EMAIL,
            'recipient' => $appointment->user->email,
            'message' => $this->message,
        ]);
    }
}