<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class FileSharedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;
    public $shared_name;
    public $shared_id;
    public $shared_to_id;
    public $file_name;
    public $file_id;

    /**
     * Create a new notification instance.
     */
    public function __construct($notifData)
    {
        $this->file_id       = $notifData['file_id'];
        $this->file_name     = $notifData['file_name'];
        $this->shared_name   = $notifData['shared_name'];
        $this->shared_id     = $notifData['shared_id'];
        $this->shared_to_id  = $notifData['shared_to_id'];
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message'       => "{$this->shared_name} shared file {$this->file_name} with you!",
            'file_id'       => $this->file_id,
            'file_name'     => $this->file_name,
            'sender_id'     => $this->shared_id,
            'receiver_id'   => $this->shared_to_id
        ];
    }

    public function broadcastOn()
    {
        return new PrivateChannel('App.Models.User.' . $this->shared_to_id);
    }
}
