<?php

return [

    'pdf' => [
        'title' => 'Zertifikat',
        'awarded_to' => 'Hiermit wird bestätigt, dass',
        'for_completing' => 'den folgenden Kurs abgeschlossen hat:',
        'date' => 'Datum',
        'code' => 'Prüfcode',
        'verify_at' => 'Echtheit prüfen unter',
    ],

    'verify' => [
        'title' => 'Zertifikat prüfen',
        'status_valid' => 'Dieses Zertifikat ist gültig.',
        'status_revoked' => 'Dieses Zertifikat wurde widerrufen.',
        'status_unknown' => 'Zu diesem Prüfcode gibt es kein Zertifikat.',
        'learner' => 'Ausgestellt für',
        'course' => 'Kurs',
        'issued_at' => 'Ausgestellt am',
        'issuer' => 'Aussteller',
        'revoked_at' => 'Widerrufen am',
        'code' => 'Prüfcode',
    ],

    'mail' => [
        'subject' => 'Dein Zertifikat: :course',
        'greeting' => 'Hallo :name,',
        'body' => 'du hast „:course“ abgeschlossen. Dein Zertifikat hängt an dieser Mail.',
        'verify' => 'Die Echtheit kann jede:r hier prüfen:',
    ],

];
