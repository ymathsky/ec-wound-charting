<?php
// Filename: api/GoogleAuth.php

class GoogleAuth {
    private $keyFile;
    private $scopes;

    public function __construct($keyFile, $scopes = ['https://www.googleapis.com/auth/cloud-platform']) {
        $this->keyFile = $keyFile;
        $this->scopes = $scopes;
    }

    public function getAccessToken() {
        if (!file_exists($this->keyFile)) {
            throw new Exception("Service account key file not found at: " . $this->keyFile);
        }

        $jsonContent = file_get_contents($this->keyFile);
        $keyData = json_decode($jsonContent, true);
        
        if (!$keyData || !isset($keyData['private_key']) || !isset($keyData['client_email'])) {
            throw new Exception("Invalid service account key file. Ensure it is a valid JSON from Google Cloud.");
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $keyData['client_email'],
            'sub' => $keyData['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => implode(' ', $this->scopes)
        ];

        $jwt = $this->base64UrlEncode(json_encode($header)) . '.' . 
               $this->base64UrlEncode(json_encode($payload));

        $signature = '';
        if (!openssl_sign($jwt, $signature, $keyData['private_key'], 'SHA256')) {
            throw new Exception("OpenSSL signing failed.");
        }
        $jwt .= '.' . $this->base64UrlEncode($signature);

        // Exchange JWT for Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For XAMPP dev env
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Failed to get access token. HTTP: $httpCode. Response: $response. Curl Error: $curlError");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
             throw new Exception("Access token not found in response: $response");
        }
        
        return $data['access_token'];
    }

    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
