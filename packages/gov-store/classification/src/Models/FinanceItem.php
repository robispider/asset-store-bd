<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

/** One Finance Division item: gov_code GGSSII with its group, subgroup and item labels. */
class FinanceItem extends Model
{
    public const STATUSES = ['held', 'active', 'retired'];

    /** II is two digits and runs across the whole group, so a group holds at most 99 items. */
    public const GROUP_CAPACITY = 99;

    public const CAPACITY_WARNING = 90;

    protected $table = 'gov_finance_items';

    protected $primaryKey = 'gov_code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['gov_code', 'group_name', 'subgroup_name', 'item_name', 'unspsc_code', 'status', 'origin',
        'source_reference', 'authority_reference', 'updated_by'];

    public function groupCode(): string
    {
        return substr($this->gov_code, 0, 2);
    }

    public function subgroupCode(): string
    {
        return substr($this->gov_code, 2, 2);
    }

    public function itemNumber(): string
    {
        return substr($this->gov_code, 4, 2);
    }

    public function label(): string
    {
        return $this->gov_code.' · '.$this->item_name;
    }

    public function path(): string
    {
        return $this->group_name.' › '.$this->subgroup_name;
    }

    public static function isCode(?string $code): bool
    {
        return is_string($code) && preg_match('/^(?!00)\d{2}(?!00)\d{2}(?!00)\d{2}$/', $code) === 1;
    }
}
