<?php

return [
    // National changes and access administration always enforce.
    'mode' => env('GOVSTORE_ACCESS_MODE', 'shadow'),
    'enforcement_date' => env('GOVSTORE_ACCESS_ENFORCEMENT_DATE'),
    'help_contact' => env('GOVSTORE_ACCESS_HELP_CONTACT'),
];
