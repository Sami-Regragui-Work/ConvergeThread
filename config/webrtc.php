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

return [
    /*
    |--------------------------------------------------------------------------
    | WebRTC ICE servers
    |--------------------------------------------------------------------------
    |
    | Public STUN is enough on many networks. For symmetric NAT / strict
    | firewalls, configure a TURN relay (coturn or a hosted provider).
    | Large group calls still need an SFU later; mesh + TURN is the current path.
    |
    */

    'ice_servers' => $servers,
];
