<?php

namespace GovStore\Tracking\Console\Commands;

use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\TrackingReceiptResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class TrackingRebuildDeliveryFactsCommand extends Command
{
    protected $signature = 'govstore:tracking-rebuild-facts {--initiative= : Exact initiative ID} {--code= : Exact tracking-code ID} {--apply : Replace verified derived facts transactionally}';

    protected $description = 'Compare receipt facts with associations; refuse repair when receipt provenance is incomplete';

    public function handle(TrackingReceiptResolver $resolver): int
    {
        if (! $this->option('initiative') && ! $this->option('code')) {
            $this->error('An exact --initiative or --code is required.');

            return self::FAILURE;
        }
        foreach (['initiative', 'code'] as $key) {
            if ($this->option($key) && ! ctype_digit((string) $this->option($key))) {
                return self::FAILURE;
            }
        }
        $codes = TrackingCode::query()->when($this->option('initiative'), fn ($q, $id) => $q->where('initiative_id', $id))
            ->when($this->option('code'), fn ($q, $id) => $q->whereKey($id))->orderBy('id')->get();
        if ($codes->isEmpty()) {
            $this->error('No matching scope.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($codes as $code) {
            $ok = DB::transaction(function () use ($code, $resolver) {
                $code = TrackingCode::whereKey($code->id)->lockForUpdate()->firstOrFail();
                $associations = TrackingAssociation::where('tracking_code_id', $code->id)->where('status', 'ACTIVE')->lockForUpdate()->get();
                $rows = $resolver->resolve($associations);
                $unknown = collect($rows)->filter(fn ($r) => $r['value'] === null || $r['date'] === null || $r['grn'] === null)->count();
                $facts = DB::table('gov_tracking_fact_deliveries')->where('tracking_code_id', $code->id);
                $this->line('Code '.$code->id.': associations='.$associations->sum('quantity').'; facts='.(clone $facts)->sum('received_qty').'; unresolved='.$unknown);
                if ($unknown) {
                    if ($this->option('apply')) {
                        $this->error('No facts changed for this code: incomplete receipt provenance.');
                    }

                    return false;
                }
                if (! $this->option('apply')) {
                    return true;
                }
                $groups = collect($rows)->groupBy(fn ($r) => json_encode([$r['location_id'], $r['category_id'], $r['model_id'], $r['manufacturer_id'], $r['supplier_id']]));
                $facts->delete();
                foreach ($groups as $group) {
                    $r = $group->first();
                    DB::table('gov_tracking_fact_deliveries')->insert([
                        'tracking_code_id' => $code->id, 'initiative_id' => $code->initiative_id, 'funding_type_id' => $code->funding_type_id, 'fiscal_year' => $code->fiscal_year,
                        'location_id' => $r['location_id'], 'category_id' => $r['category_id'], 'model_id' => $r['model_id'],
                        'manufacturer_id' => $r['manufacturer_id'], 'supplier_id' => $r['supplier_id'],
                        'geo_area_id' => DB::table('gov_location_profiles')->where('location_id', $r['location_id'])->value('geo_area_id'),
                        'received_qty' => $group->sum('quantity'), 'total_cost' => $group->sum('value'),
                        'transaction_count' => $group->pluck('source')->unique()->count(), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                return true;
            });
            $failed = $failed || ! $ok;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
