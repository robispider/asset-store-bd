<?php

namespace GovStore\Committee\Enums;

enum CommitteeStatus: string
{
    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case DISSOLVED = 'DISSOLVED';
    case EXPIRED = 'EXPIRED';
    case SUPERSEDED = 'SUPERSEDED';
}

