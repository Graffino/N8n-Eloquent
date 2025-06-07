<?php

namespace N8n\Eloquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:setup 
                            {--force : Overwrite existing configuration}
                            {--api-secret= : Set the API secret key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the n8n Eloquent integration package';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Setting up n8n Eloquent Integration...');
        
        // Check if config is already published
        $configPath = config_path('n8n-eloquent.php');
        if (File::exists($configPath) && !$this->option('force')) {
            if (!app()->runningUnitTests() && !$this->confirm('Configuration file already exists. Do you want to overwrite it?')) {
                $this->info('Setup cancelled.');
                return 0;
            }
        }
        
        // Publish configuration
        $this->call('vendor:publish', [
            '--tag' => 'n8n-eloquent-config',
            '--force' => $this->option('force')
        ]);
        
        // Generate API secret if not provided
        $apiSecret = $this->option('api-secret');
        if (!$apiSecret) {
            $apiSecret = Str::random(32);
            $this->info("Generated API secret: {$apiSecret}");
        } else {
            $this->info("Using provided API secret: {$apiSecret}");
        }
        
        // Update .env file
        $this->updateEnvFile($apiSecret);
        
        // Display setup summary
        $this->displaySetupSummary($apiSecret);
        
        $this->info('✅ n8n Eloquent Integration setup completed successfully!');
        
        return 0;
    }

    /**
     * Update the .env file with n8n configuration.
     *
     * @param  string  $apiSecret
     * @return void
     */
    protected function updateEnvFile(string $apiSecret): void
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->warn('.env file not found. Please create it manually.');
            return;
        }
        
        $envContent = File::get($envPath);
        
        // Add or update N8N_ELOQUENT_API_SECRET
        if (Str::contains($envContent, 'N8N_ELOQUENT_API_SECRET=')) {
            $envContent = preg_replace(
                '/N8N_ELOQUENT_API_SECRET=.*/',
                "N8N_ELOQUENT_API_SECRET={$apiSecret}",
                $envContent
            );
        } else {
            $envContent .= "\n# n8n Eloquent Integration\nN8N_ELOQUENT_API_SECRET={$apiSecret}\n";
        }
        
        // Add N8N_URL if not present
        if (!Str::contains($envContent, 'N8N_URL=')) {
            $n8nUrl = 'http://localhost:5678'; // Default for testing
            if (!app()->runningUnitTests()) {
                $n8nUrl = $this->ask('What is your n8n instance URL?', 'http://localhost:5678');
            }
            $envContent .= "N8N_URL={$n8nUrl}\n";
        }
        
        File::put($envPath, $envContent);
        $this->info('Updated .env file with n8n configuration.');
    }

    /**
     * Display the setup summary.
     *
     * @param  string  $apiSecret
     * @return void
     */
    protected function displaySetupSummary(string $apiSecret): void
    {
        $this->newLine();
        $this->info('📋 Setup Summary:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $this->line("🔑 API Secret: {$apiSecret}");
        $this->line('📁 Configuration: config/n8n-eloquent.php');
        $this->line('🌐 API Endpoints: /api/n8n/*');
        
        $this->newLine();
        $this->info('📖 Next Steps:');
        $this->line('1. Configure your models in config/n8n-eloquent.php');
        $this->line('2. Set up model events in the configuration file');
        $this->line('3. Register models: php artisan n8n:register-models --all');
        $this->line('4. Test the API: GET /api/n8n/models (with X-N8n-Api-Key header)');
        
        $this->newLine();
        $this->info('📚 Documentation: https://github.com/n8n-io/n8n-eloquent');
    }
} 