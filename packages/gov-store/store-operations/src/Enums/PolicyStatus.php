<?php

namespace GovStore\StoreOperations\Enums;

enum PolicyStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ARCHIVED = 'ARCHIVED';
}