<?php
/**
 * Google Indexing API Helper for Good News Nusantara
 * https://developers.google.com/search/apis/indexing-api/v3/quickstart
 */

class GoogleIndexer {

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get Google OAuth2 Access Token using JWT Service Account
     */
    public static function getAccessToken() {
        $keyPath = __DIR__ . '/google-indexing-key.json';
        if (!file_exists($keyPath)) {
            return null;
        }

        $keyData = json_decode(file_get_contents($keyPath), true);
        if (!$keyData || empty($keyData['private_key']) || empty($keyData['client_email'])) {
            return null;
        }

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $keyData['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $jwtHeader = self::base64UrlEncode(json_encode($header));
        $jwtPayload = self::base64UrlEncode(json_encode($payload));
        $dataToSign = $jwtHeader . '.' . $jwtPayload;

        $binarySignature = '';
        $privateKey = openssl_pkey_get_private($keyData['private_key']);
        if (!$privateKey) {
            return null;
        }

        openssl_sign($dataToSign, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwtSignature = self::base64UrlEncode($binarySignature);
        $jwt = $dataToSign . '.' . $jwtSignature;

        // Exchange JWT for Bearer Access Token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        $resData = json_decode($response, true);
        return $resData['access_token'] ?? null;
    }

    /**
     * Notify Google of a new or updated URL
     * $type: 'URL_UPDATED' or 'URL_DELETED'
     */
    public static function notify($url, $type = 'URL_UPDATED') {
        try {
            $token = self::getAccessToken();
            if (!$token) {
                return ['success' => false, 'error' => 'Gagal mendapatkan access token Google'];
            }

            $endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
            $body = json_encode([
                'url' => $url,
                'type' => $type
            ]);

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resData = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'response' => $resData];
            } else {
                return ['success' => false, 'code' => $httpCode, 'error' => $resData['error']['message'] ?? $response];
            }
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
