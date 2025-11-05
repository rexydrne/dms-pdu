<?php

namespace App\Mail;

use App\Models\File;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FileSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $file;
    public $sharedBy;
    public $sharedTo;
    public $shareLink;

    /**
     * Create a new message instance.
     */
    public function __construct(File $file, User $sharedBy, User $sharedTo, $shareLink)
    {
        $this->file = $file;
        $this->sharedBy = $sharedBy;
        $this->sharedTo = $sharedTo;
        $this->shareLink = $shareLink;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'File Shared Notification',
        );
    }

    public function build(){
        return $this->subject('A file has been shared with you')
                    ->markdown('emails.files.shared');
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.files.shared',
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
