<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadFileToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected File $file)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $model = $this->file;

        if (!$model->uploaded_on_cloud) {
            $fileContents = Storage::disk('local')->get($model->storage_path);
            Log::debug("Uploading file on Cloudflare R2. " . $model->storage_path);
            try {
                $success = Storage::disk('r2')->put($model->storage_path, $fileContents, 'public');
                if ($success) {
                    Storage::disk('local')->delete($model->storage_path);
                    $model->uploaded_on_cloud = 1;
                    $model->saveQuietly();
                } else {
                    Log::error('Unable to upload files to Cloudflare R2');
                }
            } catch(\Exception $e){
                Log::error("R2 Upload Error: " . $e->getMessage());
            }
        }
    }
}
