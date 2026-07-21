<?php

namespace GovStore\StoreOperations\Contracts;

use Illuminate\Support\Collection;

interface StoreDocumentInterface
{
    /**
     * Get the unique database identifier (UUID or Integer).
     */
    public function getDocumentId(): string|int;

    /**
     * Get the polymorphic document type (e.g., 'receipt', 'issue').
     */
    public function getDocumentType(): string;

    /**
     * Get the human-readable document serial number (e.g., GR-2026-000001).
     */
    public function getDocumentNumber(): string;

    /**
     * Get the active lifecycle status (e.g., 'DRAFT', 'POSTED').
     */
    public function getStatus(): string;

    /**
     * Get all line items associated with this document.
     */
    public function getLineItems(): Collection;

    /**
     * Retrieve the frozen, immutable compiled profile snapshot.
     */
    public function getCompiledProfileSnapshot(): ?array;
}