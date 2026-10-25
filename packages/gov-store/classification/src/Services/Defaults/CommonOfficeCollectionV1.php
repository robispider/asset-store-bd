<?php

namespace GovStore\Classification\Services\Defaults;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Immutable, offline installation data. Not a public upload/import executor. */
class CommonOfficeCollectionV1
{
    public const KEY = 'bd-common-office';
    public const MANIFEST_SHA256 = 'd840ca2fc4cef21ad66d30046fe2655bf3d9d2b31169467d7dd17c99bcad3692';

    public function install(?string $directory = null): void
    {
        $directory ??= __DIR__.'/../../database/data/office-defaults/v1';
        $manifestPath = $directory.'/manifest.json';
        if (! is_readable($manifestPath) || ! hash_equals(self::MANIFEST_SHA256, hash_file('sha256', $manifestPath))) {
            throw new RuntimeException('The common office v1 manifest is missing or has changed. Restore the released bundle.');
        }
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['files'] as $file => $hash) {
            $path = $directory.'/'.$file;
            if (! is_readable($path) || ! hash_equals($hash, hash_file('sha256', $path))) {
                throw new RuntimeException('The common office v1 CSV bundle is missing or has changed: '.$file);
            }
        }
        $collections = $this->csv($directory.'/collections.csv', ['key', 'name', 'name_bn', 'description', 'description_bn', 'icon']);
        $nodes = $this->csv($directory.'/nodes.csv', ['code', 'parent_code', 'level', 'title_en', 'hid', 'is_selectable']);
        $items = $this->csv($directory.'/items.csv', ['collection_key', 'code', 'section', 'title_en', 'title_bn', 'category_type', 'unit', 'aliases_en', 'aliases_bn']);
        $this->validate($collections, $nodes, $items, $manifest);

        DB::transaction(function () use ($collections, $nodes, $items, $manifest) {
            $changed = false;
            $now = now();
            foreach ($nodes as $node) {
                $existing = DB::table('gov_catalog_nodes')->where('code', $node['code'])->first();
                if ($existing) {
                    // Preserve installed versions, English labels and all local enrichment.
                    if ($existing->scheme !== 'UNSPSC' || (int) $existing->level !== (int) $node['level']
                        || (string) $existing->parent_code !== $node['parent_code'] || $existing->hid !== $node['hid']
                        || (bool) $existing->is_selectable !== (bool) $node['is_selectable']) {
                        throw new RuntimeException('An installed catalog node conflicts with common office v1: '.$node['code']);
                    }
                    continue;
                }
                DB::table('gov_catalog_nodes')->insert(array_merge($node, [
                    'parent_code' => $node['parent_code'] ?: null, 'scheme' => 'UNSPSC',
                    'version' => $manifest['reference_version'], 'created_at' => $now, 'updated_at' => $now,
                ]));
                $changed = true;
            }

            $collection = $collections[0];
            $id = DB::table('gov_catalog_collections')->where('key', self::KEY)->value('id');
            if (! $id) {
                $id = DB::table('gov_catalog_collections')->insertGetId($collection + [
                    'is_active' => true, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $changed = true;
            }

            foreach ($items as $item) {
                if (! DB::table('gov_catalog_collection_nodes')->where('collection_id', $id)->where('code', $item['code'])->exists()) {
                    DB::table('gov_catalog_collection_nodes')->insert([
                        'collection_id' => $id, 'code' => $item['code'], 'category_type' => $item['category_type'],
                        'section' => $item['section'], 'unit' => $item['unit'], 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $changed = true;
                }
                if (! DB::table('gov_catalog_enrichments')->where('code', $item['code'])->exists()) {
                    DB::table('gov_catalog_enrichments')->insert([
                        'code' => $item['code'], 'title_bn' => $item['title_bn'], 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $changed = true;
                }
                foreach (['en' => $item['aliases_en'], 'bn' => $item['title_bn'].';'.$item['aliases_bn']] as $language => $aliases) {
                    foreach (array_unique(array_filter(array_map('trim', explode(';', $aliases)))) as $alias) {
                        $key = ['code' => $item['code'], 'language' => $language, 'synonym' => $alias];
                        if (! DB::table('gov_catalog_synonyms')->where($key)->exists()) {
                            DB::table('gov_catalog_synonyms')->insert($key + ['type' => 'common', 'created_at' => $now, 'updated_at' => $now]);
                            $changed = true;
                        }
                    }
                }
            }
            if ($changed) {
                DB::table('gov_catalog_import_history')->insert([
                    'scheme' => 'UNSPSC', 'version' => 'bd-common-office-v1', 'filename' => 'office-defaults/v1/manifest.json',
                    'rows_processed' => count($items), 'warnings' => 0, 'duration_seconds' => 0,
                    'user_id' => null, 'imported_at' => $now,
                ]);
            }
        });
    }

    private function csv(string $path, array $columns): array
    {
        $handle = fopen($path, 'rb');
        try {
            if (fgetcsv($handle, 0, ',', '"', '') !== $columns) {
                throw new RuntimeException('Unexpected common office CSV header: '.basename($path));
            }
            $rows = [];
            while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($line) !== count($columns)) {
                    throw new RuntimeException('Invalid common office CSV row: '.basename($path));
                }
                $rows[] = array_combine($columns, $line);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function validate(array $collections, array $nodes, array $items, array $manifest): void
    {
        if (count($collections) !== 1 || $collections[0]['key'] !== self::KEY
            || count($nodes) !== $manifest['node_count'] || count($items) !== $manifest['item_count']) {
            throw new RuntimeException('Invalid common office collection identity or row counts.');
        }
        $byCode = [];
        foreach ($nodes as $node) {
            $code = $node['code'];
            $level = (int) $node['level'];
            $parent = $node['parent_code'];
            if (! preg_match('/^[0-9]{8}$/D', $code) || isset($byCode[$code]) || $level < 1 || $level > 4
                || $node['title_en'] === '' || $node['is_selectable'] !== ($level === 4 ? '1' : '0')
                || ($level === 1 ? $parent !== '' : (! isset($byCode[$parent]) || (int) $byCode[$parent]['level'] !== $level - 1))
                || $node['hid'] !== (($parent === '' ? '/' : $byCode[$parent]['hid']).$code.'/')) {
                throw new RuntimeException('Invalid common office catalog hierarchy: '.$code);
            }
            $byCode[$code] = $node;
        }
        $seen = [];
        foreach ($items as $item) {
            $node = $byCode[$item['code']] ?? null;
            if (! $node || $node['level'] !== '4' || isset($seen[$item['code']])
                || $item['collection_key'] !== self::KEY || $item['title_en'] !== $node['title_en'] || $item['title_bn'] === ''
                || ! in_array($item['category_type'], ['asset', 'consumable', 'accessory', 'component'], true)
                || ! in_array($item['section'], ['furniture', 'stationery', 'ict', 'printing', 'electrical', 'cleaning', 'general'], true)
                || ! in_array($item['unit'], ['piece', 'sheet', 'roll'], true)) {
                throw new RuntimeException('Invalid common office item: '.$item['code']);
            }
            $seen[$item['code']] = true;
        }
    }
}
