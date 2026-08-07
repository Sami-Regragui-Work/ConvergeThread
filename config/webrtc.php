<?php

$stun = [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
];

$turnUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TURN_URLS', ''))
)));

$servers = $stun;

if ($turnUrls !== []) {
    $servers[] = array_filter([
        'urls' => count($turnUrls) === 1 ? $turnUrls[0] : $turnUrls,
        'username' => env('TURN_USERNAME') ?: null,
        'credential' => env('TURN_CREDENTIAL') ?: null,
    ]);
}

$sfuUrl = trim((string) env('LIVEKIT_URL', ''));
$sfuKey = trim((string) env('LIVEKIT_API_KEY', ''));
$sfuSecret = trim((string) env('LIVEKIT_API_SECRET', ''));

return [
    /*
    |--------------------------------------------------------------------------
    | WebRTC ICE servers
    |--------------------------------------------------------------------------
    |
    | Public STUN is enough on many networks. For symmetric NAT / strict
    | firewalls, configure a TURN relay (coturn or a hosted provider).
    | When LiveKit SFU is configured, media uses the SFU path instead of mesh.
    |
    */

    'ice_servers' => $servers,

    /*
    |--------------------------------------------------------------------------
    | Mesh peer limit (informational / UI hint)
    |--------------------------------------------------------------------------
    |
    | Full-mesh is fine for small calls. Above this, prefer LiveKit SFU when
    | configured. Duos always stay on mesh unless SFU is forced via env.
    |
    */

    'mesh_max_peers' => (int) env('WEBRTC_MESH_MAX_PEERS', 4),

    /*
    |--------------------------------------------------------------------------
    | LiveKit SFU (optional)
    |--------------------------------------------------------------------------
    |
    | Set LIVEKIT_URL + LIVEKIT_API_KEY + LIVEKIT_API_SECRET to route group /
    | merge calls through an SFU. Small duo calls stay on mesh + TURN unless
    | LIVEKIT_FORCE_ALL=true.
    |
    | Local demo: docker run --rm -p 7880:7880 -e LIVEKIT_KEYS="devkey: secret"
    |   livekit/livekit-server --dev
    |
    */

    'sfu' => [
        'url' => $sfuUrl,
        'api_key' => $sfuKey,
        'api_secret' => $sfuSecret,
        'force_all' => filter_var(env('LIVEKIT_FORCE_ALL', false), FILTER_VALIDATE_BOOLEAN),
        'enabled' => $sfuUrl !== '' && $sfuKey !== '' && $sfuSecret !== '',
    ],
];
