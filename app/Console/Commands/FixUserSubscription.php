<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class FixUserSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:fix-user {user_id} {--status=active}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix a user\'s subscription status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $status = $this->option('status');

        $user = User::find($userId);
        if (!$user) {
            $this->error("User {$userId} not found!");
            return 1;
        }

        $this->info("User ID: {$user->id}");
        $this->info("User Email: {$user->email}");
        $this->info("User Credits: {$user->credits}");

        // Get the most recent subscription
        $subscription = $user->subscriptions()->latest()->first();

        if (!$subscription) {
            $this->error("No subscription found for user {$userId}!");
            return 1;
        }

        $this->info("\n=== Before Fix ===");
        $this->info("Subscription ID: {$subscription->id}");
        $this->info("Stripe ID: {$subscription->stripe_id}");
        $this->info("Current Status: {$subscription->stripe_status}");
        $this->info("Is Active: " . ($subscription->active() ? 'YES' : 'NO'));
        $this->info("Is Valid: " . ($subscription->valid() ? 'YES' : 'NO'));
        $this->info("User Subscribed: " . ($user->subscribed() ? 'YES' : 'NO'));

        // Update the status
        $oldStatus = $subscription->stripe_status;
        $subscription->stripe_status = $status;
        $subscription->save();

        $this->info("\n=== After Fix ===");
        $this->info("Previous Status: {$oldStatus}");
        $this->info("New Status: {$subscription->stripe_status}");
        $this->info("Is Active: " . ($subscription->active() ? 'YES' : 'NO'));
        $this->info("Is Valid: " . ($subscription->valid() ? 'YES' : 'NO'));

        // Refresh the user model to get updated subscription status
        $user->refresh();
        $this->info("User Subscribed: " . ($user->subscribed() ? 'YES' : 'NO'));

        $this->info("\n✅ User {$userId}'s subscription status has been updated from '{$oldStatus}' to '{$status}'!");

        return 0;
    }
}
