<?php

return [

    'nav' => 'Zertifikate',
    'title' => 'Zertifikate',
    'permission_manage' => 'Zertifikate ansehen und widerrufen',

    'col_learner' => 'Teilnehmer:in',
    'col_course' => 'Kurs',
    'col_issued_at' => 'Ausgestellt',
    'col_status' => 'Status',
    'col_code' => 'Prüfcode',
    'col_revoked_reason' => 'Widerrufsgrund',

    'status_valid' => 'Gültig',
    'status_revoked' => 'Widerrufen',

    'verify' => 'Prüfseite öffnen',
    'revoke' => 'Widerrufen',
    'revoke_title' => 'Zertifikat widerrufen',
    'revoke_description' => 'Die Prüfseite zeigt das Zertifikat danach als widerrufen, der Download ist gesperrt. Das lässt sich nicht rückgängig machen.',
    'revoke_reason' => 'Grund',
    'revoke_reason_help' => 'Nur im Control Panel sichtbar, nicht auf der Prüfseite.',
    'revoke_confirm' => 'Widerrufen',
    'cancel' => 'Abbrechen',
    'revoked' => 'Zertifikat :code wurde widerrufen.',

    'truncated' => 'Gezeigt werden die neuesten :limit von :total Zertifikaten.',
    'empty_heading' => 'Noch keine Zertifikate ausgestellt.',
    'empty_description' => 'Zertifikate entstehen, wenn jemand einen Kurs abschließt, oder mit php artisan certificates:issue.',
    'setup_heading' => 'Die Zertifikatstabelle gibt es noch nicht.',
    'setup_migrate_heading' => 'Migrationen ausführen',
    'setup_migrate_description' => 'php artisan migrate legt sie an.',

];
