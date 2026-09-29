<?php
/**
 * Test script to verify role-based authorization
 * Run: php test_role_auth.php
 */

echo "=== Testing Role-Based Authorization ===\n\n";

// Test 1: Login as driver
echo "Test 1: Attempting to login as driver...\n";
$loginResponse = file_get_contents('https://cpsu-motorpool-backend.onrender.com/api/login', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode([
            'email' => 'driver@example.com',
            'password' => 'password123'
        ])
    ]
]));

if ($loginResponse) {
    $login = json_decode($loginResponse, true);
    echo "✓ Login successful. Role: " . ($login['role'] ?? 'unknown') . "\n\n";
    
    $driverToken = $login['token'] ?? null;
    
    if ($driverToken) {
        // Test 2: Try to access admin endpoint as driver
        echo "Test 2: Driver attempting to access /api/drivers (SHOULD FAIL)...\n";
        $driverListResponse = @file_get_contents('https://cpsu-motorpool-backend.onrender.com/api/drivers', false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $driverToken,
                'ignore_errors' => true
            ]
        ]));
        
        $statusCode = explode(' ', $http_response_header[0])[1] ?? '000';
        
        if ($statusCode == '403') {
            echo "✓ CORRECT! Driver blocked with 403 Forbidden\n";
            $errorData = json_decode($driverListResponse, true);
            echo "  Message: " . ($errorData['message'] ?? 'No message') . "\n\n";
        } else {
            echo "✗ SECURITY BUG! Driver was NOT blocked (Status: $statusCode)\n\n";
        }
        
        // Test 3: Driver accessing allowed endpoint
        echo "Test 3: Driver accessing /api/my-trips (SHOULD SUCCEED)...\n";
        $myTripsResponse = @file_get_contents('https://cpsu-motorpool-backend.onrender.com/api/my-trips', false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $driverToken,
                'ignore_errors' => true
            ]
        ]));
        
        $statusCode = explode(' ', $http_response_header[0])[1] ?? '000';
        
        if ($statusCode == '200') {
            echo "✓ CORRECT! Driver can access their own trips\n\n";
        } else {
            echo "✗ BUG! Driver cannot access allowed endpoint (Status: $statusCode)\n\n";
        }
    }
} else {
    echo "✗ Login failed (credentials might need updating)\n\n";
}

echo "=== Test Complete ===\n";
