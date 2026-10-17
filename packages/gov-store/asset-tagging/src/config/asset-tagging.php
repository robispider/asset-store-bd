<?php

return [
    // Tag formats are code (D2). Office users choose facts; nobody edits a format.
    'formats' => [
        'fd18-v1' => \GovStore\AssetTagging\Formats\FinanceDivisionV1Format::class,
        'native-v1' => \GovStore\AssetTagging\Formats\NativeSequenceV1Format::class,
    ],

    // Receiving sources that can issue a Govt. Tag. Donation, transfer and confiscated receipts get
    // standard tags until decision D-04 settles their acquisition date.
    'govt_purchase_types' => ['Purchase'],

    // G5: this installation is the single place tags are issued and clones are fenced. The deployment
    // operator sets this once the database has no live clones (D-07 keeps cross-installation out of scope).
    'single_issuer' => (bool) env('ASSET_TAGS_SINGLE_ISSUER', false),

    // G3: legacy 18-digit tags that are not Govt. Tags must be reviewed before an office is enabled.
    // Set to the review date once custodians have verified the preflight list.
    'legacy_reviewed_at' => env('ASSET_TAGS_LEGACY_REVIEWED_AT'),

    // A Govt. Tag sequence (NNNN) runs 1..9999 per 14-digit prefix and is never reset or wrapped.
    'sequence_capacity' => 9999,
];
