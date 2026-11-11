<?php
// Synthetic local demo only. These values are not live credentials.
// The container guard refuses to boot unless MEMBERS_DEMO=1 and the
// checks in members-demo/php/demo-guard.php all pass.
return [
    'base_url' => 'http://localhost:8080',
    'db' => [
        'host' => 'db',
        'port' => 3306,
        'name' => 'members_demo',
        'user' => 'members_demo',
        'pass' => 'demo-db-not-for-production',
        'socket' => '',
    ],
    'app_secret' => 'demo-only-app-secret-not-for-production-use-0001',
    // Local catcher only. HostGator stays on driver "mail" (PHP mail()) unless
    // someone deliberately sets driver "smtp" and a mailbox password. See docs/MAIL_SETTINGS.md.
    'mail' => [
        'driver' => 'smtp',
        'from' => 'members@localhost',
        'from_name' => 'Invisible Society demo',
        'smtp' => [
            'host' => 'mailpit',
            'port' => 1025,
            'secure' => '',
            'user' => '',
            'pass' => '',
        ],
    ],
    'site_url' => '/',
    'paypal' => [
        'me_name' => '',
        'currency' => 'USD',
        'link_template' => '',
    ],
    'display_timezone' => 'America/New_York',
    'wait_counts_from' => 'opened',
    'max_pdf_mb' => 10,
    'admin_notify_email' => '',
    'facilitators' => [
        'min_degree' => 1,
        'lead' => 0,
        'ken_up_to' => 0,
        'ken_when_none' => false,
        'ken_name' => 'Ken (the Invisible Society)',
        'ken_note' => '',
        'order' => 'name',
        'reapprove_on_pay_change' => true,
    ],
    'notify' => ['member' => true, 'facilitator' => true],
    'artwork' => [
        'placeholder' => 'none',
        'alt' => ['default' => 'Artwork by Liz'],
    ],
    'min_password_length' => 10,
    'signup_auto_signin' => true,
    // The imported sample leaves this true, which hides Degrees from members.
    // The isolated demo turns it off so login, progress, and facilitator flows can run.
    'degrees_coming_soon' => false,
    'debug' => false,
];
