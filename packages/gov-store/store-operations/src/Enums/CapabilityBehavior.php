<?php

namespace GovStore\StoreOperations\Enums;

enum CapabilityBehavior: string
{
    case ENFORCE = 'ENFORCE';
    case DISABLE = 'DISABLE';
    case INHERIT = 'INHERIT';
}