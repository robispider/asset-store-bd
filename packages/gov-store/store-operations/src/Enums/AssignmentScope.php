<?php

namespace GovStore\StoreOperations\Enums;

enum AssignmentScope: string
{
    case GLOBAL = 'GLOBAL';     // Applies universally
    case COMPANY = 'COMPANY';   // Applies to a specific Tenant/Ministry
    case LOCATION = 'LOCATION'; // Applies to a specific physical office
    case NATIVE = 'NATIVE';     // Applies to native Snipe-IT Major Types, Categories, or Models
}