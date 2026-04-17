<?php

class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL    = 'claude-sonnet-4-20250514';

    public static function call(
        string $system,
        string $user,
        string $anthropicKey,
        int $maxTokens = 1000
    ): array {
        $payload = self::buildPayload($system, $user, $maxTokens);

        $ch = curl_init(self::ENDPOINT);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => self::buildHeaders($anthropicKey),
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        // Preserve legacy behavior exactly (no exceptions)
        if ($curlErr) {
            return [
                'text'  => '',
                'error' => 'Anthropic curl error: ' . $curlErr
            ];
        }

        if ($status !== 200) {
            return [
                'text'  => '',
                'error' => 'Anthropic API error ' . $status . ': ' . substr($response, 0, 200)
            ];
        }

        $data = json_decode($response, true);

        return [
            'text'  => $data['content'][0]['text'] ?? '',
            'error' => ''
        ];
    }

    private static function buildPayload(string $system, string $user, int $maxTokens): string
    {
        return json_encode([
            'model'      => self::MODEL,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $user
                ]
            ],
        ]);
    }

    private static function buildHeaders(string $anthropicKey): array
    {
        return [
            'Content-Type: application/json',
            'x-api-key: ' . $anthropicKey,
            'anthropic-version: 2023-06-01',
        ];
    }
}