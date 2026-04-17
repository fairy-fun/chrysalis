<?php
declare(strict_types=1);

function http_post_json(string $url, array $payload, array $headers = []): array {
    $ch = curl_init($url);

    $defaultHeaders = ['Content-Type: application/json'];
    $headers = array_merge($defaultHeaders, $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'status' => $status,
        'body' => $response,
        'curl_error' => $curlErr,
    ];
}

function http_get(string $url, array $headers = []): array {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'status' => $status,
        'body' => $response,
        'curl_error' => $curlErr,
    ];
}