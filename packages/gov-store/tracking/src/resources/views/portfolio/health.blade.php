@php($badgeStatus=match($p['health']) {'attention'=>'stalled','setup'=>'draft','closed'=>'complete',default=>$p['health']})
<x-gs::status-badge :status="$badgeStatus" :label="__('govtracking::portfolio.health.'.$p['health'])" />
