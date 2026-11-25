<?php

$adminEmails = array_map(
    static fn (string $email): string => strtolower(trim($email)),
    explode(',', (string) env('STUDYDESK_ADMIN_EMAILS', '')),
);

return [
    'admin_emails' => array_values(array_filter($adminEmails)),
];
