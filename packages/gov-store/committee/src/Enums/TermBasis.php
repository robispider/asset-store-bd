<?php

namespace GovStore\Committee\Enums;

enum TermBasis: string
{
    case FIXED = 'FIXED';
    case FISCAL_YEAR = 'FISCAL_YEAR';
    case SINGLE_MATTER = 'SINGLE_MATTER';
    case UNTIL_FURTHER_ORDER = 'UNTIL_FURTHER_ORDER';
}

