<?php

namespace Shortinc\N8nEloquent\Console\Commands;

use Illuminate\Console\Command;
use Shortinc\N8nEloquent\Services\ModelDiscoveryService;

class RegisterModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'n8n:register-models 
                            {--whitelist : Register all models in the whitelist}
                            {--blacklist : Register all models not in the blacklist}
                            {--all : Register all discovered models}
                            {--model= : Register a specific model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register Laravel Eloquent models with n8n';

    /**
     * The model discovery service.
     *
     * @var \N8n\Eloquent\Services\ModelDiscoveryService
     */
    protected $modelDiscovery;

    /**
     * Create a new command instance.
     *
     * @param  \N8n\Eloquent\Services\ModelDiscoveryService  $modelDiscovery
     * @return void
     */
    public function __construct(ModelDiscoveryService $modelDiscovery)
    {
        parent::__construct();
        $this->modelDiscovery = $modelDiscovery;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Determine the mode
        $mode = 'whitelist'; // Default mode
        
        if ($this->option('blacklist')) {
            $mode = 'blacklist';
        } elseif ($this->option('all')) {
            $mode = 'all';
        }
        
        // Register a specific model if provided
        if ($this->option('model')) {
            $modelClass = $this->option('model');
            $this->registerModel($modelClass);
            return 0;
        }
        
        // Update the configuration mode temporarily
        config(['n8n-eloquent.models.mode' => $mode]);
        
        // Get the models based on the mode
        $models = $this->modelDiscovery->getModels();
        
        $this->info("Discovered {$models->count()} models for registration in {$mode} mode.");
        
        // Register each model
        $registeredCount = 0;
        
        foreach ($models as $modelClass) {
            if ($this->registerModel($modelClass)) {
                $registeredCount++;
            }
        }
        
        $this->info("Successfully registered {$registeredCount} models with n8n.");
        
        return 0;
    }

    /**
     * Register a specific model.
     *
     * @param  string  $modelClass
     * @return bool
     */
    protected function registerModel(string $modelClass): bool
    {
        $metadata = $this->modelDiscovery->getModelMetadata($modelClass);
        
        if ($metadata === null) {
            $this->error("Model {$modelClass} not found or not accessible.");
            return false;
        }
        
        $this->info("Registering model: {$modelClass}");
        
        // Here we would typically send a registration request to n8n
        // But since we're using a webhook-based approach, we just need to
        // provide the information about how to subscribe to this model's events
        
        $this->line("Model metadata:");
        $this->table(
            ['Attribute', 'Value'],
            collect($metadata)->map(function ($value, $key) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                return [$key, $value];
            })->values()->all()
        );
        
        return true;
    }
} 