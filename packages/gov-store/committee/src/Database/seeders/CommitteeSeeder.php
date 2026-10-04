<?php

namespace GovStore\Committee\Database\seeders;

use GovStore\Committee\Models\{SeatRole,CommitteeType};
use Illuminate\Database\Seeder;

class CommitteeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['chairperson','Chairperson','সভাপতি',true,false,true],
            ['convener','Convener','আহ্বায়ক',true,false,true],
            ['member_secretary','Member-Secretary','সদস্য সচিব',false,true,true],
            ['member','Member','সদস্য',false,false,true],
            ['technical_expert','Technical Expert','কারিগরি বিশেষজ্ঞ',false,false,true],
            ['co_opted','Co-opted Member','কো-অপ্ট সদস্য',false,false,true],
            ['observer','Observer','পর্যবেক্ষক',false,false,false],
        ] as $i=>[$code,$en,$bn,$presiding,$secretary,$strength]) {
            SeatRole::firstOrCreate(['code'=>$code],['name_en'=>$en,'name_bn'=>$bn,'is_presiding'=>$presiding,'is_secretary'=>$secretary,'counts_toward_strength'=>$strength,'sort_order'=>$i,'is_active'=>true]);
        }
        foreach ([
            ['GRIC','Goods Receiving & Inspection Committee','পণ্য গ্রহণ ও পরিদর্শন কমিটি','inventory',0,'WARN'],
            ['TIC','Technical Inspection Committee','কারিগরি পরিদর্শন কমিটি','inventory',1,'WARN'],
            ['SVC','Stock Verification Committee','মজুদ যাচাই কমিটি','inventory',1,'BLOCK'],
            ['BOS','Board of Survey','বোর্ড অব সার্ভে','disposal',1,'BLOCK'],
            ['DSP','Disposal Committee','নিষ্পত্তি কমিটি','disposal',0,'BLOCK'],
        ] as [$code,$en,$bn,$category,$external,$severity]) {
            CommitteeType::firstOrCreate(['owner_company_id'=>null,'code'=>$code],['name_en'=>$en,'name_bn'=>$bn,'category'=>$category,'default_term_basis'=>'FISCAL_YEAR','allowed_scope_types'=>['office','store'],'allow_concurrent'=>false,'is_active'=>false,
                'composition_policy'=>['strength'=>['min'=>3,'max'=>5,'odd_only'=>false],'presiding'=>['exactly'=>1,'roles'=>['chairperson','convener']],
                    'secretary'=>['min'=>0,'max'=>1],'external'=>['min'=>$external,'outside'=>'office'],'technical_expert'=>['min'=>0],
                    'incompatible_duties'=>[['duty'=>'storekeeper','severity'=>$severity]],'declaration_required'=>false,'quorum'=>['min_present'=>2],'nomination'=>'BY_POST_OR_NAME','max_term_months'=>12]]);
        }
    }
}

