<?php
// Copy this file to config.php (inside the "app" folder) OR to
// /home/YOURCPANELUSER/ken-members-config.php (safer: outside public_html).
// Fill in the values. NEVER upload your real config to GitHub or share it.
return [
    // Full web address of the members folder, no trailing slash.
    'base_url' => 'https://YOURSITE.example/members',

    // Database (cPanel > MySQL Databases). On HostGator the names start with your cPanel name.
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'cpaneluser_members',
        'user' => 'cpaneluser_members',
        'pass' => 'CHANGE-ME',
        'socket' => '',          // leave empty on HostGator
    ],

    // Long random text (40+ characters). Used to scramble rate-limit keys. Make your own.
    'app_secret' => 'CHANGE-ME-TO-A-LONG-RANDOM-STRING',

    // Email. 'mail' = the host's built-in mail (simplest). 'smtp' = a mailbox you made in cPanel.
    // 'log' = writes emails to storage/mail.log (testing only, never on the live site).
    'mail' => [
        'driver' => 'mail',
        'from' => 'members@YOURSITE.example',   // must be an address on your own domain
        'from_name' => 'Invisible Society',
        'smtp' => [
            'host' => 'mail.YOURSITE.example',
            'port' => 465,
            'secure' => 'ssl',   // 'ssl' (port 465) or 'tls' (port 587)
            'user' => 'members@YOURSITE.example',
            'pass' => 'CHANGE-ME',
        ],
    ],

    // Where the main website lives (for the header links). '/' if the members folder is on the same site.
    'site_url' => '/',

    // ---- Piece 2: Degrees, Part A (dues) and lessons ----
    // Ken's PayPal.Me name: the part after paypal.me/ in his link. Part A buttons send members to
    // https://www.paypal.me/NAME/AMOUNT. Without it, members see "link not set up yet".
    'paypal' => [
        'me_name' => '',                 // e.g. 'KensName'
        'currency' => 'USD',
        'link_template' => '',           // optional: a full custom link; {amount} {currency} {ref} are filled in. Must be https.
    ],
    // Times are shown in this zone (a PHP zone name). Dates in the database stay in UTC.
    'display_timezone' => 'America/New_York',
    // When does the waiting time between Degrees start?
    //   'opened' = the day the Degree opened for the member (Part A confirmed)
    //   'part_b' = the day Part B of that Degree was confirmed
    'wait_counts_from' => 'opened',
    'max_pdf_mb' => 10,                  // largest PDF Ken can upload (the host may have a lower limit)
    'admin_notify_email' => '',          // an email address that is told when a member says "I paid". Empty = nobody.
    // 'text' => ['part_b_help' => 'Your own words about how Part B is paid.'],

    // ---- Piece 3: Member Facilitators and Part B (tuition). The site never handles Part B money. ----
    'facilitators' => [
        'min_degree' => 3,               // a member can ask to be a Facilitator after completing this Degree
        'lead' => 0,                     // a Facilitator can take Part B of Degree N after completing N + lead (0 = that Degree itself)
        'ken_up_to' => 3,                // Ken is always a choice (and the fallback confirmer) for Degrees 1..this
        'ken_when_none' => true,         // also offer Ken when a Degree has no eligible Facilitator
        'ken_name' => 'Ken (the Invisible Society)',
        'ken_note' => '',                // optional note shown to members who pay Part B to Ken
        'order' => 'name',               // order of the list: 'name' (A-Z) or 'shuffle' (fair, changes daily)
        'reapprove_on_pay_change' => true, // a Facilitator who changes how they are paid waits for Ken again
    ],
    'notify' => ['member' => true, 'facilitator' => true],   // email the member when a payment is confirmed; email a Facilitator when a member says "I paid"
    // Liz's artwork: put files in members/assets/art/ named home.jpg, dashboard.jpg, degree.jpg or degree-5.jpg (webp/png also work).
    'artwork' => [
        'placeholder' => 'admin',        // empty slots: 'admin' = only Ken sees a marked space, 'all' = everyone, 'none' = nobody
        'alt' => ['default' => 'Artwork by Liz'],   // words a screen reader says; e.g. 'home' => 'Liz\'s painting of ...'
    ],

    'min_password_length' => 10,
    // Sign-up: true = a new member is signed in straight away (no confirmation email needed).
    // false = they must click the link in a confirmation email first (safer, but needs working email).
    'signup_auto_signin' => true,
    // true = members see "Degrees — Coming Soon" instead of the Degrees, fees, payments and Facilitators.
    // Ken (admin) still sees everything. Set to false when the Degrees are ready to open.
    'degrees_coming_soon' => true,
    'debug' => false,        // keep false on the live site
];
