<?php

/**
 * Simple ML API client using cURL
 */

function ml_predict(string $url, array $payload, int $timeoutSeconds = 10): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new Exception("Failed to init cURL");
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new Exception("Failed to encode ML payload as JSON");
    }

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS      => $json,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CONNECTTIMEOUT  => min(3, $timeoutSeconds),
        CURLOPT_TIMEOUT         => $timeoutSeconds,
    ]);

    $resp = curl_exec($ch);

    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception("ML cURL error: " . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        throw new Exception("ML response not valid JSON (HTTP $httpCode)");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $detail = $data["detail"] ?? "Unknown error";
        throw new Exception("ML HTTP $httpCode: " . (is_string($detail) ? $detail : "Error"));
    }

    return $data;
}
