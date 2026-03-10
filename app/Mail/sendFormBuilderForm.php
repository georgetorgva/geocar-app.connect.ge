<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class sendFormBuilderForm extends Mailable
{
    use Queueable, SerializesModels;

    public $params, $sendFields, $subject;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $this->params = $params;
        $this->sendFields = _cv($params,['sendFields']);
        $this->subject = _cv($params,['subject']);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->markdown('dynamic-email')->subject($this->subject)->with($this->sendFields);
    }
}
