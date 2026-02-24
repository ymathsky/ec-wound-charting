<?php
// Filename: api/google_cloud_config.php

// 1. Go to Google Cloud Console (https://console.cloud.google.com/)
// 2. Create a Project or select an existing one.
// 3. Enable "Vertex AI API".
// 4. Go to IAM & Admin > Service Accounts > Create Service Account.
// 5. Grant it "Vertex AI User" role.
// 6. Create a Key (JSON) and download it.
// 7. Rename it to 'service_account.json' and place it in the 'ec' root folder (c:\xampp\htdocs\ec\service_account.json).

// Replace with your actual Project ID
define('GC_PROJECT_ID', 'ecwoundcharting'); 

// Region (us-central1 is standard)
define('GC_LOCATION', 'us-central1');

// Path to the downloaded JSON key file
define('GC_SERVICE_ACCOUNT_JSON', __DIR__ . '/../service_account.json'); 

// Model to use (e.g., gemini-1.5-pro-001, gemini-1.0-pro)
define('GC_VERTEX_MODEL', 'gemini-1.5-pro-001');
