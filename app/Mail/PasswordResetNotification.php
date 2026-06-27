<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;

    /**
     * Create a new message instance.
     */
    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisation de votre mot de passe - MyPharma',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $frontendUrl = rtrim(env('APP_FRONTEND_URL', config('app.url')), '/');

        $resetUrl = $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ]);

        return new Content(
            view: 'emails.password-reset',
            with: [
                'resetUrl' => $resetUrl,
                'email'    => $this->email,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
