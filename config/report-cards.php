<?php

return [
    'layout_version' => 'diniyyah-f4-v1',
    // Excel paperSize 14: Folio/F4, 8.5 × 13 inches, as in the supplied template.
    'paper' => ['width_mm' => 215.9, 'height_mm' => 330.2, 'margin_mm' => 6, 'top_mm' => 9, 'bottom_mm' => 0],
    'issue_place' => env('REPORT_CARD_ISSUE_PLACE', 'Purbalingga'),
    'principal_name' => 'Ustadz Ahmad Nurul Huda',
    'principal_signature_file' => 'ustadz-ahmad-nurul-huda-signature.png',
    'principal_title' => env('REPORT_CARD_PRINCIPAL_TITLE', 'Kepala Kelompok Tahfidz'),
    'logo_file' => 'griya-quran-transparent.png',
    'chromium_binary' => env('REPORT_CARD_CHROMIUM_BINARY'),
];
