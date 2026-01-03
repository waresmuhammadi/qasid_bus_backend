<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketBookedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $trip;

    public function __construct(Ticket $ticket, Trip $trip)
    {
        $this->ticket = $ticket;
        $this->trip = $trip;
    }

   public function build()
{
    return $this
        ->from('info@qasid.org', 'Qasid')
        ->subject('🎫 بلیط شما با موفقیت ثبت شد')
        ->view('emails.ticket_user');
}
}





