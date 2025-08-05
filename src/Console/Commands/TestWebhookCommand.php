<?php

namespace N8nEloquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use N8nEloquent\Services\WebhookService;

class TestWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:test-webhook 
                            {--webhook-url=http://localhost:5678/webhook/test : The webhook URL to test}
                            {--user-name=Test User : Name for the test user}
                            {--user-email=test@example.com : Email for the test user}
                            {--skip-subscription : Skip creating webhook subscription}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test webhook functionality by creating a user and triggering webhook events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Starting n8n Webhook Test...');
        $this->newLine();

        $webhookUrl = $this->option('webhook-url');
        $userName = $this->option('user-name');
        $userEmail = $this->option('user-email');
        $skipSubscription = $this->option('skip-subscription');

        try {
            // Step 1: Create webhook subscription (unless skipped)
            if (!$skipSubscription) {
                $this->info('📡 Creating webhook subscription...');
                $webhookService = App::make(WebhookService::class);
                
                $subscription = $webhookService->subscribe([
                    'model' => 'App\\Models\\User',
                    'events' => ['created', 'updated'],
                    'webhook_url' => $webhookUrl,
                    'properties' => ['id', 'name', 'email', 'created_at', 'updated_at']
                ]);

                $this->info("✅ Webhook subscription created with ID: {$subscription['id']}");
                $this->line("   Model: App\\Models\\User");
                $this->line("   Events: created, updated");
                $this->line("   URL: {$webhookUrl}");
                $this->newLine();
            } else {
                $this->warn('⏭️  Skipping webhook subscription creation');
                $this->newLine();
            }

            // Step 2: Get User model
            $userModel = App::make('App\\Models\\User');
            
            // Step 3: Create a test user (this should trigger 'created' webhook)
            $this->info('👤 Creating test user...');
            $user = $userModel::create([
                'name' => $userName,
                'email' => $userEmail,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);

            $this->info("✅ User created successfully!");
            $this->line("   ID: {$user->id}");
            $this->line("   Name: {$user->name}");
            $this->line("   Email: {$user->email}");
            $this->line("   Created: {$user->created_at}");
            $this->newLine();

            // Step 4: Update the user (this should trigger 'updated' webhook)
            $this->info('🔄 Updating test user...');
            $user->update([
                'name' => $userName . ' (Updated)',
            ]);

            $this->info("✅ User updated successfully!");
            $this->line("   New Name: {$user->name}");
            $this->line("   Updated: {$user->updated_at}");
            $this->newLine();

            // Step 5: Show webhook subscription status
            if (!$skipSubscription) {
                $this->info('📊 Checking webhook subscription status...');
                $subscriptions = $webhookService->getSubscriptions();
                $activeCount = collect($subscriptions)->where('active', true)->count();
                $this->info("✅ Active subscriptions: {$activeCount}");
                $this->newLine();
            }

            // Step 6: Summary
            $this->info('🎉 Webhook test completed successfully!');
            $this->newLine();
            $this->comment('Expected webhook calls:');
            $this->line('1. POST to ' . $webhookUrl . ' (User Created Event)');
            $this->line('2. POST to ' . $webhookUrl . ' (User Updated Event)');
            $this->newLine();
            $this->comment('Check your n8n workflow or webhook endpoint to verify the calls were received.');

            // Step 7: Cleanup option
            if ($this->confirm('Would you like to delete the test user?', true)) {
                $user->delete();
                $this->info('🗑️  Test user deleted successfully.');
            }

            if (!$skipSubscription && $this->confirm('Would you like to remove the test webhook subscription?', false)) {
                $webhookService->unsubscribe([
                    'model' => 'App\\Models\\User',
                    'events' => ['created', 'updated'],
                    'webhook_url' => $webhookUrl
                ]);
                $this->info('🗑️  Webhook subscription removed successfully.');
            }

        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->newLine();
            $this->comment('Stack trace:');
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
} 