<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public User $inviter,
        public string $role,
        public string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->workspace->name} on 7Sight",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workspace-invitation',
            with: [
                'registerUrl' => config('app.url').'/register?'.http_build_query([
                    'email' => $this->email,
                    'workspace' => $this->workspace->name,
                ]),
            ],
        );
    }
}
