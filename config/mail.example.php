<?php

return [
    // Use "smtp" for localhost development. Use "mail" only if php.ini/sendmail is configured.
    'transport' => 'smtp',

    // The inbox that receives portfolio contact messages.
    'to' => 'mudassir9290@gmail.com',

    // For SMTP, this should usually match your authenticated mailbox.
    'from' => 'your-email@gmail.com',
    'from_name' => 'Portfolio Contact',

    // Keep true while testing locally so the browser response includes the SMTP/PHP mail error.
    'debug' => true,

    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password',
        'encryption' => 'tls',
        'timeout' => 15,
    ],
];
