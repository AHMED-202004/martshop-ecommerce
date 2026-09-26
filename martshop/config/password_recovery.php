<?php

return [
    // Enable only after configuring and testing a real outgoing mail transport.
    'mail_enabled' => env('PASSWORD_RESET_MAIL_ENABLED', false),
    'url' => env('PASSWORD_RESET_URL', env('APP_URL', 'http://localhost')),
];
