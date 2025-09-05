<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillJobUserIds extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'jobs:backfill-user-ids
                           {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Backfill user_id and file_name for existing jobs by matching with uploads';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
        }

        // Get all jobs with null user_id
        $jobsWithoutUsers = DB::table('jobs')
            ->whereNull('user_id')
            ->orWhereNull('file_name')
            ->get();

        if ($jobsWithoutUsers->isEmpty()) {
            $this->info('No jobs found that need user ID backfilling.');
            return self::SUCCESS;
        }

        $this->info("Found {$jobsWithoutUsers->count()} jobs without user information.");

        $updated = 0;
        $failed = 0;

        foreach ($jobsWithoutUsers as $job) {
            try {
                // Try to decode the job payload to extract upload information
                $payload = json_decode($job->payload, true);

                if (!$payload || !isset($payload['data']['commandName'])) {
                    $this->warn("Job {$job->id}: Cannot parse payload");
                    $failed++;
                    continue;
                }

                // Look for ProcessUploadJob
                if (str_contains($payload['data']['commandName'], 'ProcessUploadJob')) {
                    // Extract upload ID from the payload
                    $uploadId = null;
                    if (isset($payload['data']['command'])) {
                        $command = unserialize($payload['data']['command']);
                        if (isset($command->uploadId)) {
                            $uploadId = $command->uploadId;
                        }
                    }

                    if (!$uploadId) {
                        $this->warn("Job {$job->id}: Cannot extract upload ID from payload");
                        $failed++;
                        continue;
                    }

                    // Find the corresponding upload
                    $upload = Upload::find($uploadId);
                    if (!$upload) {
                        $this->warn("Job {$job->id}: Upload {$uploadId} not found");
                        $failed++;
                        continue;
                    }

                    // Update the job with user information
                    if (!$isDryRun) {
                        DB::table('jobs')
                            ->where('id', $job->id)
                            ->update([
                                'user_id' => $upload->user_id,
                                'file_name' => $upload->original_name,
                            ]);
                    }

                    $this->line("Job {$job->id}: " . ($isDryRun ? 'Would update' : 'Updated') . " with user {$upload->user_id} and file '{$upload->original_name}'");
                    $updated++;
                } else {
                    // For other job types, try to match by timing and assign to admin user
                    $adminUser = DB::table('users')->where('email', 'like', '%admin%')->first();
                    if ($adminUser && !$isDryRun) {
                        DB::table('jobs')
                            ->where('id', $job->id)
                            ->update([
                                'user_id' => $adminUser->id,
                                'file_name' => 'Unknown Job',
                            ]);
                    }

                    $this->line("Job {$job->id}: " . ($isDryRun ? 'Would assign' : 'Assigned') . " to admin user (non-upload job)");
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("Job {$job->id}: Error - " . $e->getMessage());
                $failed++;
            }
        }

        $this->info("Summary:");
        $this->info("- " . ($isDryRun ? 'Would update' : 'Updated') . ": {$updated} jobs");
        $this->info("- Failed: {$failed} jobs");

        if ($isDryRun) {
            $this->warn('This was a DRY RUN. Run without --dry-run to make actual changes.');
        }

        return self::SUCCESS;
    }
}
