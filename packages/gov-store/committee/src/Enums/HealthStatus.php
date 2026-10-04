<?php

namespace GovStore\Committee\Enums;

enum HealthStatus: string
{
    case OPERABLE = 'OPERABLE';
    case AT_RISK = 'AT_RISK';
    case INOPERABLE = 'INOPERABLE';
}

