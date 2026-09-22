<?php

return [

    'permission' => 'Manage certificate settings',

    'groups' => [
        'template' => [
            'title' => 'Certificate template',
            'description' => 'What the PDF and the verification page show besides name, course and date. Applies to newly rendered PDFs.',
        ],
        'delivery' => [
            'title' => 'Delivery',
            'description' => 'Whether the certificate is mailed when it is issued.',
        ],
    ],

    'fields' => [
        'template_issuer_name' => ['label' => 'Issuer', 'description' => 'Name above the title and on the verification page.'],
        'template_logo' => ['label' => 'Logo', 'description' => 'Asset reference, e.g. assets::logos/logo.png.'],
        'template_signature' => ['label' => 'Signature', 'description' => 'Asset reference of a signature image.'],
        'template_signatory_name' => ['label' => 'Signed by', 'description' => 'Name under the signature.'],
        'template_signatory_title' => ['label' => 'Title', 'description' => 'Role under the name, e.g. Course lead.'],
        'template_accent_color' => ['label' => 'Accent colour', 'description' => 'Hex value, e.g. #1f2937. Border and title.'],
        'template_footer' => ['label' => 'Footer', 'description' => 'Small print at the bottom of the PDF.'],
        'mail_enabled' => ['label' => 'Send by mail', 'description' => 'Mails the PDF to the owner\'s address when it is issued.'],
    ],

];
