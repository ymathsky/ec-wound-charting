<?php
// Filename: api/get_maps_api_key.php

// This file securely provides the Google Maps API key to the frontend.

header("Content-Type: application/json; charset=UTF-8");

// Include the database connection file where the key is stored
require_once '../db_connect.php';

// Check if the GOOGLE_MAPS_API_KEY constant is defined
if (defined('GOOGLE_MAPS_API_KEY')) {
    http_response_code(200);
    echo json_encode(['apiKey' => GOOGLE_MAPS_API_KEY]);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Google Maps API key is not configured on the server.']);
}
?>
