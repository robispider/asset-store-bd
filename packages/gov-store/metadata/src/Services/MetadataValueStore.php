<?php

namespace GovStore\Metadata\Services;

use GovStore\Metadata\Contracts\MetadataValueSchema;
use GovStore\Metadata\Events\MetadataValueChanged;
use GovStore\Metadata\Models\MetadataValue;
use GovStore\Metadata\Support\StaleMetadataRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Typed read/write of gov_metadata_values. Packages register a schema per namespace, the same way
 * providers register with MetadataRegistry. Every write carries the expected revision and is audited.
 */
class MetadataValueStore
{
    public const MAX_PAYLOAD_BYTES = 16384;

    /** @var array<string, MetadataValueSchema|string> */
    private array $schemas = [];

    public function register(string $namespace, MetadataValueSchema|string $schema): void
    {
        $this->schemas[$namespace] = $schema;
    }

    public function registered(string $namespace): bool
    {
        return isset($this->schemas[$namespace]);
    }

    public function schema(string $namespace): MetadataValueSchema
    {
        $schema = $this->schemas[$namespace] ?? throw new InvalidArgumentException("Metadata namespace [{$namespace}] is not registered.");
        if (is_string($schema)) {
            $schema = $this->schemas[$namespace] = app($schema);
        }

        return $schema;
    }

    public function find(string $subjectType, int $subjectId, string $namespace): ?MetadataValue
    {
        return MetadataValue::where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->where('namespace', $namespace)->first();
    }

    public function payload(string $subjectType, int $subjectId, string $namespace): ?array
    {
        return $this->find($subjectType, $subjectId, $namespace)?->payload;
    }

    /** Read under a shared lock so a posting re-checks the value it uses (no-op on SQLite). */
    public function shared(string $subjectType, int $subjectId, string $namespace): ?MetadataValue
    {
        return MetadataValue::where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->where('namespace', $namespace)->sharedLock()->first();
    }

    /** All rows of one namespace, keyed by subject id. */
    public function all(string $namespace, string $subjectType): \Illuminate\Support\Collection
    {
        return MetadataValue::where('namespace', $namespace)->where('subject_type', $subjectType)->get()->keyBy('subject_id');
    }

    /**
     * Write a payload. $expectedRevision is the revision the editor saw (null when the row did not exist).
     * A mismatch throws StaleMetadataRevision carrying the current value for a compare-and-choose banner.
     */
    public function put(string $subjectType, int $subjectId, string $namespace, array $payload, ?int $expectedRevision, ?int $actorId, ?string $reason = null): MetadataValue
    {
        $schema = $this->schema($namespace);
        if (! in_array($subjectType, $schema->subjectTypes(), true)) {
            throw new InvalidArgumentException("Subject type [{$subjectType}] is not allowed in [{$namespace}].");
        }

        return DB::transaction(function () use ($schema, $subjectType, $subjectId, $namespace, $payload, $expectedRevision, $actorId, $reason) {
            $row = MetadataValue::where('subject_type', $subjectType)->where('subject_id', $subjectId)
                ->where('namespace', $namespace)->lockForUpdate()->first();
            if (($row?->revision) !== $expectedRevision) {
                throw new StaleMetadataRevision($row);
            }
            $normalized = $schema->normalize($subjectType, $subjectId, $payload, $row?->payload);
            $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
                throw ValidationException::withMessages(['payload' => __('govmeta::metadata.payload_too_large')]);
            }
            [$scopeType, $scopeId] = $schema->scope($subjectType, $subjectId);
            $before = $row?->payload;
            $row ??= new MetadataValue(['subject_type' => $subjectType, 'subject_id' => $subjectId, 'namespace' => $namespace, 'revision' => 0]);
            $row->fill(['scope_type' => $scopeType, 'scope_id' => (int) $scopeId, 'payload' => $normalized,
                'revision' => (int) $row->revision + 1, 'updated_by' => $actorId]);
            $row->save();

            Log::info('GovStore metadata value changed', ['subject' => "{$subjectType}:{$subjectId}", 'namespace' => $namespace,
                'revision' => $row->revision, 'actor' => $actorId, 'reason' => $reason, 'before' => $before, 'after' => $normalized]);
            event(new MetadataValueChanged($subjectType, $subjectId, $namespace, $before, $normalized, $row->revision, $actorId, $reason));

            return $row;
        });
    }
}
