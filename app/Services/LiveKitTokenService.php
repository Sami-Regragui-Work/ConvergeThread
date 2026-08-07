<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class LiveKitTokenService
{
    public function enabled(): bool
    {
        $url = (string) Config::get('webrtc.sfu.url', '');
        $key = (string) Config::get('webrtc.sfu.api_key', '');
        $secret = (string) Config::get('webrtc.sfu.api_secret', '');

        return $url !== '' && $key !== '' && $secret !== '';
    }

    public function url(): ?string
    {
        $url = trim((string) Config::get('webrtc.sfu.url', ''));

        return $url !== '' ? $url : null;
    }

    /**
     * Mint a LiveKit access token (HS256 JWT) for room join + publish/subscribe.
     */
    public function mint(string $roomName, string $identity, string $name, int $ttlSeconds = 7200): string
    {
        $apiKey = (string) Config::get('webrtc.sfu.api_key');
        $apiSecret = (string) Config::get('webrtc.sfu.api_secret');
        $now = time();

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'nbf' => $now - 10,
            'exp' => $now + max(60, $ttlSeconds),
            'name' => $name,
            'video' => [
                'roomJoin' => true,
                'room' => $roomName,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        return $this->encodeJwt($header, $payload, $apiSecret);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    private function encodeJwt(array $header, array $payload, string $secret): string
    {
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
