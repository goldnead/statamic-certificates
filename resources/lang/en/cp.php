<?php

return [

    'nav' => 'Certificates',
    'title' => 'Certificates',
    'permission_manage' => 'View and revoke certificates',

    'col_learner' => 'Learner',
    'col_course' => 'Course',
    'col_issued_at' => 'Issued',
    'col_status' => 'Status',
    'col_code' => 'Code',

    'status_valid' => 'Valid',
    'status_revoked' => 'Revoked',

    'verify' => 'Open verification page',
    'revoke' => 'Revoke',
    'revoke_title' => 'Revoke certificate',
    'revoke_description' => 'The verification page will show the certificate as revoked and the download is blocked. This cannot be undone.',
    'revoke_reason' => 'Reason',
    'revoke_reason_help' => 'Only shown in the Control Panel, not on the verification page.',
    'revoke_confirm' => 'Revoke',
    'cancel' => 'Cancel',
    'revoked' => 'Certificate :code revoked.',

    'truncated' => 'Showing the newest :limit of :total certificates.',
    'empty_heading' => 'No certificates issued yet.',
    'empty_description' => 'Certificates are issued when someone completes a course, or with php artisan certificates:issue.',
    'setup_heading' => 'The certificates table does not exist yet.',
    'setup_migrate_heading' => 'Run the migrations',
    'setup_migrate_description' => 'php artisan migrate creates it.',

];
