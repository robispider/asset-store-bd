<?php

namespace GovStore\StoreOperations\Traits;

use GovStore\StoreOperations\Models\DocumentAttachment;

trait HasStoreAttachments
{
    /**
     * Polymorphic relationship for PDF/Image uploads
     */
    public function attachments()
    {
        return $this->morphMany(DocumentAttachment::class, 'document');
    }
}