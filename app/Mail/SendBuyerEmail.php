<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendBuyerEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $data;
    public $products;
    public $subject;

    public function __construct($params)
    {

        $this->data = _cv($params,['data']);
        $this->products = _cv($params, ['data','cart_info']);
        if(!is_array($this->products)){
            $this->products = json_decode($this->products, true);
        }
        $this->subject = $params['subject'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.sendBuyerEmail')->subject($this->subject)->with(['data' => $this->data, 'products' => $this->products]);
    }
}
