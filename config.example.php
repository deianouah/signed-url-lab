<?php
// Demo project settings. Generate a new secret before any non-local use.
return [
    // HMAC secret used to sign URLs. Keep this value private.
    'secret' => 'replace-with-a-long-random-secret',

    // Signed URL lifetime in seconds
    'ttl' => 60,

    // Bind a signed URL to the requesting IP address
    'bind_ip' => false,

    // Origins allowed to read responses in the browser (CORS)
    'allowed_origins' => [
        'http://localhost:3000',
    ],

    // Test accounts
    'users' => [
        'sub'  => ['password' => 'sub123',  'subscribed' => true,  'name' => 'Subscribed'],
        'free' => ['password' => 'free123', 'subscribed' => false, 'name' => 'Not subscribed'],
    ],

    // Files available under private/
    'files' => [
        'video.mp4' => 'video/mp4',
    ],
];
