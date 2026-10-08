<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class RecoveryCodeMail extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function build(): static
    {
        return $this->subject('Mã xác nhận Badminton Hub')->text('mail.recovery');
    }
}
