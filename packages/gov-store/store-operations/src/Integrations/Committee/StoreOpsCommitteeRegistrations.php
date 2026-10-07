<?php

namespace GovStore\StoreOperations\Integrations\Committee;

use GovStore\Committee\Contracts\PurposeRegistry;
use GovStore\Committee\DTOs\PurposeDefinition;

class StoreOpsCommitteeRegistrations
{
    public static function register(): void
    {
        if (! interface_exists(PurposeRegistry::class)) { return; }
        foreach ([
            ['storeops.receipt.inspection','Goods receipt inspection','পণ্য গ্রহণ ও পরিদর্শন',['GRIC']],
            ['storeops.receipt.inspection.technical','Technical goods inspection','কারিগরি পণ্য পরিদর্শন',['TIC','GRIC']],
            ['storeops.stock.verification','Stock verification','মজুদ যাচাই',['SVC']],
            ['storeops.disposal.survey','Unserviceable asset survey','অকেজো সম্পদের সার্ভে',['BOS']],
            ['storeops.disposal.execution','Asset disposal','সম্পদ নিষ্পত্তি',['DSP']],
        ] as [$code,$en,$bn,$types]) {
            app(PurposeRegistry::class)->declare(new PurposeDefinition($code,$en,$bn,['office','store'],$types,'gov-store/store-operations'));
        }
    }
}
