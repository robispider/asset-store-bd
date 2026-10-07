<?php

namespace GovStore\Committee\Enums;

enum ResolutionStatus: string
{
    case FOUND = 'FOUND';
    case NOT_FOUND = 'NOT_FOUND';
    case INOPERABLE = 'INOPERABLE';
    case AMBIGUOUS = 'AMBIGUOUS';
    case CONFLICT = 'CONFLICT';
}

