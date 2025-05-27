<?php

require_once __DIR__ . '/vendor/autoload.php';

// Manually include our package files
require_once __DIR__ . '/vendor/n8n/eloquent/src/Services/ModelDiscoveryService.php';
require_once __DIR__ . '/vendor/n8n/eloquent/src/Services/WebhookService.php';

use N8n\Eloquent\Services\ModelDiscoveryService;

echo "=== N8N ELOQUENT PACKAGE VALIDATION ===\n\n";

// Test 1: Model Discovery Service
echo "1. Testing ModelDiscoveryService...\n";

$config = [
    'models' => [
        'namespace' => 'App\\Models',
        'directory' => __DIR__ . '/app/Models',
        'mode' => 'all',
        'whitelist' => [],
        'blacklist' => []
    ]
];

$modelDiscovery = new ModelDiscoveryService($config);
$models = $modelDiscovery->discoverModels();

echo "   Found " . count($models) . " models:\n";
foreach ($models as $model) {
    echo "   - " . $model['name'] . " (" . $model['class'] . ")\n";
}

// Test 2: Check if User model is discovered
echo "\n2. Testing User model discovery...\n";
$userModel = null;
foreach ($models as $model) {
    if ($model['name'] === 'User') {
        $userModel = $model;
        break;
    }
}

if ($userModel) {
    echo "   ✓ User model found\n";
    echo "   - Class: " . $userModel['class'] . "\n";
    echo "   - Properties: " . count($userModel['properties']) . "\n";
    echo "   - Methods: " . count($userModel['methods']) . "\n";
} else {
    echo "   ✗ User model not found\n";
}

// Test 3: Check if UserCounter model is discovered
echo "\n3. Testing UserCounter model discovery...\n";
$userCounterModel = null;
foreach ($models as $model) {
    if ($model['name'] === 'UserCounter') {
        $userCounterModel = $model;
        break;
    }
}

if ($userCounterModel) {
    echo "   ✓ UserCounter model found\n";
    echo "   - Class: " . $userCounterModel['class'] . "\n";
} else {
    echo "   ✗ UserCounter model not found\n";
}

echo "\n=== VALIDATION COMPLETE ===\n"; 