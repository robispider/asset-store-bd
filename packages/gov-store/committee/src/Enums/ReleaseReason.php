<?php

namespace GovStore\Committee\Enums;

enum ReleaseReason: string
{
    case TRANSFER = 'TRANSFER';
    case RETIREMENT = 'RETIREMENT';
    case PROMOTION = 'PROMOTION';
    case RESIGNATION = 'RESIGNATION';
    case REMOVAL = 'REMOVAL';
    case DEATH = 'DEATH';
    case TERM_END = 'TERM_END';
    case RECONSTITUTION = 'RECONSTITUTION';
    case CORRECTION = 'CORRECTION';
}

