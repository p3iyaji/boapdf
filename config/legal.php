<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal documents
    |--------------------------------------------------------------------------
    |
    | Effective date shown on Terms of Use and Privacy Policy pages.
    | Update this when you publish material changes to either document.
    */
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-08-08'),

    /*
    |--------------------------------------------------------------------------
    | Operator contact
    |--------------------------------------------------------------------------
    |
    | Used in legal pages for privacy requests and terms questions.
    | Falls back to the mail from address when unset.
    */
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

    /*
    |--------------------------------------------------------------------------
    | Operator / governing law (optional)
    |--------------------------------------------------------------------------
    |
    | Leave blank until confirmed with counsel. When set, Terms of Use will
    | reference these values in the governing-law section.
    */
    'operator_name' => env('LEGAL_OPERATOR_NAME'),
    'governing_law' => env('LEGAL_GOVERNING_LAW'),

];
