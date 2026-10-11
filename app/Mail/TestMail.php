<?php

namespace App\Mail;

use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Testmail von der Systemseite. Zugleich das Muster fuer eigene Mails:
 * immer eine Textfassung UND eine HTML-Fassung.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $triggeredBy) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Testmail von '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test',
            text: 'mail.test-text',
            with: [
                'brand' => Branding::current(),
                'link' => config('app.url'),
            ],
        );
    }
}
