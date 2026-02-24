<?php
// Filename: api/vertex_auth.php
// Purpose: Handles Google Vertex AI Authentication using Service Account JSON

class VertexAuth {
    private $keyFile;
    private $tokenFile;

    public function __construct() {
        $this->keyFile = __DIR__ . '/../config/service_account.json';
        $this->tokenFile = __DIR__ . '/../config/access_token.json';
    }

    public function getAccessToken() {
        // 1. Check if we have a valid cached token
        if (file_exists($this->tokenFile)) {
            $cached = json_decode(file_get_contents($this->tokenFile), true);
            if ($cached && isset($cached['access_token']) && isset($cached['expires_at'])) {
                if (time() < $cached['expires_at'] - 60) { // Buffer of 60 seconds
                    return $cached['access_token'];
                }
            }
        }

        // 2. Generate new token
        return $this->fetchNewToken();
    }

    private function fetchNewToken() {
        if (!file_exists($this->keyFile)) {
            throw new Exception("Service account key file not found at: " . $this->keyFile);
        }

        $keyData = json_decode(file_get_contents($this->keyFile), true);
        if (!$keyData) {
            throw new Exception("Invalid service account JSON.");
        }

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $keyData['client_email'],
            'sub' => $keyData['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/cloud-platform'
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;
        $signature = '';
        
        if (!openssl_sign($signatureInput, $signature, $keyData['private_key'], 'SHA256')) {
            throw new Exception("Failed to sign JWT.");
        }

        $base64UrlSignature = $this->base64UrlEncode($signature);
        $jwt = $signatureInput . "." . $base64UrlSignature;

        // Exchange JWT for Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new Exception("Failed to obtain access token: " . $response);
        }

        // Cache the token
        $data['expires_at'] = time() + $data['expires_in'];
        file_put_contents($this->tokenFile, json_encode($data));

        return $data['access_token'];
    }

    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    public function getProjectId() {
        if (!file_exists($this->keyFile)) return null;
        $keyData = json_decode(file_get_contents($this->keyFile), true);
        return $keyData['project_id'] ?? null;
    }
}
