<?php

return [
    // Role restrictions enforce by default; shadow requires an explicit override.
    // National changes and access administration always enforce in either mode.
    'mode' => env('GOVSTORE_ACCESS_MODE', 'enforce'),
    'enforcement_date' => env('GOVSTORE_ACCESS_ENFORCEMENT_DATE'),
    'help_contact' => env('GOVSTORE_ACCESS_HELP_CONTACT'),
];
