<?php

namespace GovStore\CustomRequests\DTOs;

class CatalogItem
{
    public string $type;
    public int $id;
    public string $name;
    public string $category;
    public int $available_qty;
    public string $image_url;
    public int $created_timestamp; // For sorting by Date
    public array $details;         // For the bullet points in List View

    public function __construct(
        string $type,
        int $id,
        string $name,
        string $category,
        int $available_qty,
        string $image_url,
        int $created_timestamp,
        array $details = []
    ) {
        $this->type = $type;
        $this->id = $id;
        $this->name = $name;
        $this->category = $category;
        $this->available_qty = $available_qty;
        $this->image_url = $image_url;
        $this->created_timestamp = $created_timestamp;
        $this->details = $details;
    }
}