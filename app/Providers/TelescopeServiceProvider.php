<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();
        $this->addN8nTagging();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Add custom tagging for n8n-related requests.
     */
    protected function addN8nTagging(): void
    {
        Telescope::tag(function (IncomingEntry $entry) {
            $tags = [];

            // Tag n8n API requests
            if ($entry->type === 'request') {
                $uri = $entry->content['uri'] ?? '';
                
                if (str_contains($uri, '/api/n8n/')) {
                    $tags[] = 'n8n-api';
                    
                    // More specific tags
                    if (str_contains($uri, '/models')) {
                        $tags[] = 'n8n-models';
                    } elseif (str_contains($uri, '/webhooks')) {
                        $tags[] = 'n8n-webhooks';
                    }
                }

                // Tag requests with n8n API key
                $headers = $entry->content['headers'] ?? [];
                if (isset($headers['x-n8n-api-key'])) {
                    $tags[] = 'n8n-authenticated';
                }
            }

            // Tag n8n-related model events
            if ($entry->type === 'model' && isset($entry->content['action'])) {
                $tags[] = 'n8n-model-event';
            }

            // Tag n8n-related exceptions
            if ($entry->type === 'exception') {
                $message = $entry->content['message'] ?? '';
                if (str_contains(strtolower($message), 'n8n') || 
                    str_contains(strtolower($message), 'webhook')) {
                    $tags[] = 'n8n-error';
                }
            }

            return $tags;
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-n8n-api-key',  // Hide n8n API key for security
            'x-laravel-signature',  // Hide webhook signatures
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
} 