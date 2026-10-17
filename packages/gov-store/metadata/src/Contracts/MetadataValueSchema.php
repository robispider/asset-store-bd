<?php

namespace GovStore\Metadata\Contracts;

/**
 * A typed namespace in gov_metadata_values. Packages register one per namespace; the store never
 * writes a payload that the schema has not normalized.
 */
interface MetadataValueSchema
{
    /** Allowed subject types for this namespace, e.g. ['office']. */
    public function subjectTypes(): array;

    /** The owner scope [scope_type, scope_id] for a subject; re-derived on every write. */
    public function scope(string $subjectType, int $subjectId): array;

    /**
     * Validate and normalize a payload for this subject. Throw a ValidationException on bad input.
     * $current is the stored payload (null on first write) so schemas can protect in-use entries.
     */
    public function normalize(string $subjectType, int $subjectId, array $payload, ?array $current): array;
}
