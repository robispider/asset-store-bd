<?php

namespace GovStore\Committee\Domain;

final class MemoNumber
{
    public static function normalize(string $memo): string
    {
        $memo = strtr($memo, ['০'=>'0','১'=>'1','২'=>'2','৩'=>'3','৪'=>'4','৫'=>'5','৬'=>'6','৭'=>'7','৮'=>'8','৯'=>'9','।'=>'.','–'=>'-','—'=>'-']);
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $memo)));
    }
}

