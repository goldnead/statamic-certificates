<?php

return [

    'permission' => 'Zertifikat-Einstellungen verwalten',

    'groups' => [
        'template' => [
            'title' => 'Zertifikatsvorlage',
            'description' => 'Was auf dem PDF und der Prüfseite neben Name, Kurs und Datum steht. Gilt für neu erzeugte PDFs.',
        ],
        'delivery' => [
            'title' => 'Zustellung',
            'description' => 'Ob das Zertifikat beim Ausstellen per Mail verschickt wird.',
        ],
    ],

    'fields' => [
        'template_issuer_name' => ['label' => 'Aussteller', 'description' => 'Name über dem Titel und auf der Prüfseite.'],
        'template_logo' => ['label' => 'Logo', 'description' => 'Asset-Referenz, z. B. assets::logos/logo.png.'],
        'template_signature' => ['label' => 'Unterschrift', 'description' => 'Asset-Referenz eines Bilds der Unterschrift.'],
        'template_signatory_name' => ['label' => 'Unterzeichnet von', 'description' => 'Name unter der Unterschrift.'],
        'template_signatory_title' => ['label' => 'Funktion', 'description' => 'Rolle unter dem Namen, z. B. Kursleitung.'],
        'template_accent_color' => ['label' => 'Akzentfarbe', 'description' => 'Hex-Wert, z. B. #1f2937. Rahmen und Titel.'],
        'template_footer' => ['label' => 'Fußzeile', 'description' => 'Kleiner Text unten auf dem PDF.'],
        'mail_enabled' => ['label' => 'Per Mail zustellen', 'description' => 'Schickt das PDF beim Ausstellen an die hinterlegte Adresse.'],
    ],

];
