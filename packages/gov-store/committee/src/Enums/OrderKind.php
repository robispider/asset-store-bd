<?php

namespace GovStore\Committee\Enums;

enum OrderKind: string
{
    case CONSTITUTION = 'CONSTITUTION';
    case AMENDMENT = 'AMENDMENT';
    case RECONSTITUTION = 'RECONSTITUTION';
    case EXTENSION = 'EXTENSION';
    case SUSPENSION = 'SUSPENSION';
    case RESUMPTION = 'RESUMPTION';
    case DISSOLUTION = 'DISSOLUTION';
    case CORRIGENDUM = 'CORRIGENDUM';
}

