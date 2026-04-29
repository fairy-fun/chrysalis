<?php
$body = file_get_contents('php://input');
$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen((string)$body),
        'content' => $body,
        'timeout' => 10,
        'ignore_errors' => true,
    ],
]);
$response = @file_get_contents('https://antheapeche7.us.auth0.com/oauth/token', false, $context);
foreach ($http_response_header ?? [] as $h) {
    if (stripos($h, 'Content-Type:') === 0) header($h);
}
echo $response !== false ? $response : json_encode(['error' => 'token_proxy_failed']);