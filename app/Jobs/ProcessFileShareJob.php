<?php

namespace App\Jobs;

use App\Mail\FileSharedMail;
use App\Models\File;
use App\Models\Shareable;
use App\Models\User;
use App\Notifications\FileSharedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ProcessFileShareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $file;
    public $sharedBy;
    public $targetUser;
    public $shareRecord;

    /**
     * Create a new job instance.
     */
    public function __construct(File $file, User $sharedBy, User $targetUser, Shareable $shareRecord)
    {
        $this->file = $file;
        $this->sharedBy = $sharedBy;
        $this->targetUser = $targetUser;
        $this->shareRecord = $shareRecord;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notifData = [
            'file_id' => $this->file->id,
            'file_name' => $this->file->name,
            'shared_name' => $this->sharedBy->fullname,
            'shared_id' => $this->sharedBy->id,
            'shared_to_id' => $this->targetUser->id,
        ];

        $this->targetUser->notify(new FileSharedNotification($notifData));

        $shareLink = "https://dms-pdu-production.up.railway.app/share/{$this->shareRecord->token}";

        \Mail::to($this->targetUser->email)->send(
            new FileSharedMail($this->file, $this->sharedBy, $this->targetUser, $shareLink)
        );

    }
}
