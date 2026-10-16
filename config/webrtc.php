<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ICE servers
    |--------------------------------------------------------------------------
    |
    | Public STUN only by default. No TURN server is provided out of the
    | box — peers behind restrictive/symmetric NAT will fail to connect
    | without one. Set TURN_URL/TURN_USERNAME/TURN_CREDENTIAL to add one
    | (a paid service or a self-hosted coturn instance) without any code
    | changes.
    */

    'ice_servers' => array_values(array_filter([
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
        env('TURN_URL') ? [
            'urls' => env('TURN_URL'),
            'username' => env('TURN_USERNAME'),
            'credential' => env('TURN_CREDENTIAL'),
        ] : null,
    ])),

];
