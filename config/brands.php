<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Registry
    |--------------------------------------------------------------------------
    |
    | The org's public identities. The toolkit ships an empty registry —
    | each app (or org) publishes this file and registers its own brands;
    | with no registry the brand machinery is inert. Every entry keys a
    | Bcl\Toolkit\Brand\Brand value ("slug" below) and provides:
    |
    |   'name'              display name
    |   'domain'            the brand's public host (Brand::url() builds on it)
    |   'from_address'      mail From address (DMARC-align its domain)
    |   'from_name'         mail From name
    |   'mailer'            named mailer from config/mail.php (null = default;
    |                       the toolkit registers a per-brand mailer mirroring
    |                       the smtp mailer when none is defined)
    |   'website'           marketing site URL (mail footers)
    |   'address'           postal address (mail footers)
    |   'base_domain'       optional: where publish serves this brand's sites
    |   'team_email_domain' optional: email domain marking the brand's team
    |   'logo_white' / 'logo_white_png'  logo paths on the brand domain
    |   'theme'             color/font tokens consumed by the mail layout
    |                       (primary, accent, link, link_dark, highlight,
    |                       surface, heading_font, body_font, font_head)
    |
    | 'default' names the brand used when a host doesn't match any entry
    | (Brand::fromHost) and for org-level UI copy (the SSO login hint).
    |
    */

    'scheme' => env('BRAND_URL_SCHEME', 'https'),

    'default' => env('BRAND_DEFAULT'),

    'registry' => [],

];
