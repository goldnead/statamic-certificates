<?php

return [

    'pdf' => [
        'title' => 'Certificate of Completion',
        'awarded_to' => 'This is to certify that',
        'for_completing' => 'has completed the course',
        'date' => 'Date',
        'code' => 'Certificate code',
        'verify_at' => 'Verify at',
    ],

    'verify' => [
        'title' => 'Verify a certificate',
        'status_valid' => 'This certificate is valid.',
        'status_revoked' => 'This certificate has been revoked.',
        'status_unknown' => 'There is no certificate with this code.',
        'learner' => 'Issued to',
        'course' => 'Course',
        'issued_at' => 'Issued on',
        'issuer' => 'Issuer',
        'revoked_at' => 'Revoked on',
        'code' => 'Code',
    ],

    'mail' => [
        'subject' => 'Your certificate: :course',
        'greeting' => 'Hello :name,',
        'body' => 'you have completed ":course". Your certificate is attached.',
        'verify' => 'Anyone can verify it here:',
    ],

];
