<?php

echo "🔍 Simple API Test\n";
echo "==================\n\n";

$apiKey = 'test-secret-key-for-integration';
$baseUrl = 'http://127.0.0.1:8002';

echo "Testing: {$baseUrl}/api/n8n/models\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/n8n/models');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-N8n-Api-Key: ' . $apiKey,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);

if ($response === false) {
    echo "❌ Curl error: " . curl_error($ch) . "\n";
} else {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "✅ HTTP Code: $httpCode\n";
    echo "✅ Response length: " . strlen($response) . " bytes\n";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo "✅ JSON decoded successfully\n";
            echo "✅ Found " . count($data['models']) . " models\n";
        } else {
            echo "❌ Failed to decode JSON\n";
        }
    }
}

curl_close($ch); 