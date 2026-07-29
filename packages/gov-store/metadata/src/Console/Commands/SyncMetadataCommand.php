<?php

namespace GovStore\Metadata\Console\Commands;

use Illuminate\Console\Command;
use GovStore\Metadata\Registry\MetadataRegistry;
use GovStore\Metadata\Compiler\MetadataCompiler;
use GovStore\Metadata\Support\LogicalSchema;

class SyncMetadataCommand extends Command
{
    protected $signature = 'govstore:metadata-sync-phase2';
    protected $description = 'Compiles registered provider schemas using the Metadata Compiler (Phase 2)';

    public function handle(MetadataRegistry $registry, MetadataCompiler $compiler): int
    {
        $this->info('Initializing GovStore Metadata Compiler System...');

        $providers = $registry->getProviders();

        if (empty($providers)) {
            $this->warn('No providers are currently registered.');
            return 0;
        }

        foreach ($providers as $provider) {
            $this->info("Consuming Provider Manifest: {$provider->getName()}");

            // Build a clean Logical Schema from the provider's fields
            $logicalSchema = new LogicalSchema();
            $logicalSchema->addFields($provider->getFields());

            // Run compiled synchronization
            $fieldsetName = $provider->getName();
            $compiledFieldset = $compiler->compile($fieldsetName, $logicalSchema);

            $this->line("Successfully compiled Fieldset: '{$compiledFieldset->name}' (Fields Sync Count: " . $compiledFieldset->fields()->count() . ")");
        }

        $this->info('Phase 2 Compiler Compilation Process Complete.');
        return 0;
    }
}

