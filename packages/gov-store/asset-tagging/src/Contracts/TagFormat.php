<?php

namespace GovStore\AssetTagging\Contracts;

/** A tag format selected by version through TagFormatFactory (D2). */
interface TagFormat
{
    public function version(): string;

    /** The shared part of every tag built from these facts (the counter scope), or null when there is none. */
    public function prefix(array $facts): ?string;

    public function encode(array $facts, int $sequence): string;

    /** Decoded segments, or null when the string is not a valid tag of this format. */
    public function decode(string $tag): ?array;

    /**
     * Labelled segments for <x-gs::code-segments>: [['value' => '09', 'label' => …, 'tone' => …], …].
     * A null sequence is drawn as "allocated when posted".
     */
    public function segments(array $facts, ?int $sequence): array;
}
