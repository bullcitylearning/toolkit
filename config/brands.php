<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Registry
    |--------------------------------------------------------------------------
    |
    | The org's public identities, shared by every BCL app. Mail, links, and
    | views are themed from these tokens (pulse sends per-brand survey mail;
    | publish maps its org concept onto the same slugs). base_domain is where
    | publish SERVES files (bcltraining.net); team_email_domain is who-can-see
    | corporate IDENTITY (bcltraining.com). They are deliberately different
    | domains.
    |
    | Each brand may name a mailer from config/mail.php whose sending domain
    | aligns with its From domain (DMARC). The toolkit registers a default
    | mailer per brand that mirrors the app's smtp mailer; apps that need
    | distinct per-brand transports define mail.mailers.<brand> themselves.
    |
    */

    'scheme' => env('BRAND_URL_SCHEME', 'https'),

    'bcl' => [
        'name' => 'BCL',
        'domain' => env('BCL_DOMAIN', 'pulse.bcltraining.com'),
        'from_address' => env('BCL_MAIL_FROM', 'surveys@bcltraining.com'),
        'from_name' => 'BCL',
        // Named mailer from config/mail.php; null = the app default mailer.
        'mailer' => env('BCL_MAILER'),
        'website' => 'https://www.bcltraining.com',
        'address' => '600 Park Offices Drive, Suite 300, Durham, NC 27709',
        'base_domain' => env('PUBLISH_BCL_DOMAIN', 'bcltraining.net'),
        'team_email_domain' => env('PUBLISH_BCL_TEAM_DOMAIN', 'bcltraining.com'),
        'logo_white' => 'brands/bcl/logo-white-orange-underline.svg',
        'logo_white_png' => 'brands/bcl/logo-white-orange-underline.png',
        'theme' => [
            'primary' => '#002139',        // Oxford Blue — headers, buttons
            'accent' => '#FC5130',         // Orange Soda — highlights, selected states
            'link' => '#067BC2',           // Star Command Blue — links on white/platinum only
            'link_dark' => '#005D95',
            'highlight' => '#ECC30B',      // Jonquin Yellow
            'surface' => '#EBE9E9',        // Platinum background tint
            'heading_font' => "'Poppins', sans-serif",
            'body_font' => "'Roboto', sans-serif",
            'font_head' => '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">',
        ],
    ],

    'bcb' => [
        'name' => 'Bull City Blue',
        'domain' => env('BCB_DOMAIN', 'pulse.bullcityblue.com'),
        'from_address' => env('BCB_MAIL_FROM', 'surveys@bullcityblue.com'),
        'from_name' => 'Bull City Blue',
        // Named mailer from config/mail.php; null = the app default mailer.
        'mailer' => env('BCB_MAILER'),
        'website' => 'https://www.bullcityblue.com',
        'address' => '600 Park Offices Drive, Suite 300, Durham, NC 27709',
        'team_email_domain' => env('PUBLISH_BCB_TEAM_DOMAIN', 'bullcityblue.com'),
        'logo_white' => 'brands/bcb/logo-white.svg',
        'logo_white_png' => 'brands/bcb/logo-white.png',
        'theme' => [
            'primary' => '#00618D',        // BCB Blue — headers, buttons (white text passes AA; teal does not)
            'accent' => '#009CBB',         // BCB Teal — accents, non-text elements
            'link' => '#00618D',
            'link_dark' => '#1B2029',      // Dark Navy
            'highlight' => '#EF7A22',      // Warm orange accent
            'surface' => '#D8D9D8',        // Light Gray
            'heading_font' => "'Metropolis', 'Open Sans', sans-serif",
            'body_font' => "'Metropolis', 'Open Sans', sans-serif",
            'font_head' => '<style>@font-face{font-family:"Metropolis";src:url("/brands/bcb/fonts/Metropolis-Regular.otf") format("opentype");font-weight:400;font-display:swap}@font-face{font-family:"Metropolis";src:url("/brands/bcb/fonts/Metropolis-SemiBold.otf") format("opentype");font-weight:600;font-display:swap}</style>',
        ],
    ],

];
