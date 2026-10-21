(function(){
'use strict';
var TODAY = new Date('2026-10-19T12:00:00');
var BASE = 'http://snipeit.local/gov-store/admin/tracking/initiatives';

/* ------------------------------------------------------------------ strings */
var S = {
 en: {
  crumbHome:'Tracking', pageTitle:'Programme operations', pageSub:'Every programme you can see, how its deliveries are going and who to talk to next.',
  newProgramme:'New programme', navProgrammes:'Programmes', navOffices:'Offices', navFunding:'Funding types',
  st_all:'All', st_Active:'Operational', st_Planning:'In setup', st_Closed:'Closed', st_Archived:'Archived',
  search:'Search programme, owner or person', fundingAll:'All funding', mine:'Only programmes I work on', showing:'{n} of {t} programmes',
  programme:'Programme', health:'Health', delivered:'Delivered vs plan', value:'Value received', offices:'Offices reached', codes:'Tracking codes',
  team:'Team', lastReceipt:'Last receipt', activeDraft:'active · draft', expected:'Expected',
  unavailable:'Unavailable', incomplete:'Receipts incomplete', crore:'crore', lakh:'lakh',
  waiting:'{n} waiting', noAllocation:'No allocation set',
  h_on_pace:'On pace', h_behind:'Behind', h_attention:'Needs attention', h_setup:'In setup', h_closed:'Closed', h_archived:'Archived',
  c_on_pace:'On pace', c_behind:'Behind', c_stalled:'Stalled', c_over_allocation:'Over allocation', c_complete:'Complete',
  sev_critical:'Critical', sev_serious:'Serious', sev_warning:'Warning',
  issues:'{n} issues', issue:'1 issue', noIssues:'Nothing needs attention',
  r_HEAD:'Operation head', r_OFFICER:'Operation officer', r_SUPPORT:'Support', r_OBSERVER:'Observer',
  next:'Next step', contactAbout:'Contact {p} about {what}.', overdue:'overdue deliveries', setupNext:'{p} can finish setup.', noHead:'No operation head assigned yet.',
  monitor:'Monitor', manage:'Manage', report:'Progress report', properties:'Properties', open:'Open',
  openMonitor:'Open monitor', continueSetup:'Continue setup', viewReport:'View report', openFull:'Open full monitor',
  setupOf:'Setup {d} of {t}', ready_team:'Operation team assigned', ready_code:'First tracking code created', ready_active:'One code active and receiving',
  assignTeam:'Assign team', createCode:'Create code', activateCode:'Activate a code',
  verdictWarn:'{n} operational programmes need you', verdictWarn1:'1 operational programme needs you', verdictOk:'All operational programmes are on pace',
  startWith:'Start with {t}: {issue}', allClear:'Next: check the programmes in setup.',
  k_operational:'Operational programmes', k_value:'Value received this year', k_offices:'Offices reached', k_codes:'Tracking codes',
  k_valueCap:'{g} receipts · {s} suppliers', k_valueInc:'Incomplete for {n} programme(s)', k_officesCap:'{w} offices still waiting', k_codesCap:'active · draft',
  mixOn:'{n} on pace', mixBehind:'{n} behind', mixAtt:'{n} need attention',
  ppBehind:'{x} pp behind plan', ppAhead:'{x} pp ahead of plan', onPlan:'On plan', finalDelivery:'Final delivery',
  daysAgo:'{n} days ago', today:'today', yesterday:'yesterday', monthsAgo:'{n} months ago', never:'No receipts yet',
  updated:'Updated {d}', closedOn:'Closed {d}', archivedOn:'Archived {d}',
  hello_m:'Good morning', hello_a:'Good afternoon', hello_e:'Good evening',
  helloLine:'{a} operational · {b} in setup · {c} need you today',
  needsYou:'Needs you', needsHint:'The most serious problem in each operational programme, worst first, with the person to contact.', moreIssues:'and {n} more in this programme',
  showAll:'Show all {n}', showFewer:'Show fewer', operationalSec:'Operational', setupSec:'In setup', setupHint:'These programmes are not receiving yet. The checklist shows what is left.',
  closedSec:'Closed and archived', youTeam:'Your team', noTeam:'No team yet', more:'+{n} more',
  empty:'No programmes match these filters.', clear:'Clear filters',
  keyHint:'Use ↑ and ↓ to move between programmes', back:'All programmes', trend:'Delivery through the year', actual:'Delivered', expectedLine:'Expected',
  noCodesYet:'No tracking codes yet', purposeMissing:'No purpose written yet',
  expand:'Show details for {t}', collapse:'Hide details for {t}', sortBy:'Sort by {c}'
 },
 bn: {
  crumbHome:'ট্র্যাকিং', pageTitle:'কর্মসূচির কার্যক্রম', pageSub:'আপনার দৃশ্যমান প্রতিটি কর্মসূচি, সরবরাহের অগ্রগতি এবং এরপর কার সাথে কথা বলবেন।',
  newProgramme:'নতুন কর্মসূচি', navProgrammes:'কর্মসূচি', navOffices:'কার্যালয়সমূহ', navFunding:'অর্থায়নের ধরন',
  st_all:'সব', st_Active:'সক্রিয়', st_Planning:'পরিকল্পনাধীন', st_Closed:'বন্ধ', st_Archived:'আর্কাইভকৃত',
  search:'কর্মসূচি, মালিক বা ব্যক্তি খুঁজুন', fundingAll:'সব অর্থায়ন', mine:'শুধু আমার কর্মসূচি', showing:'{t}টির মধ্যে {n}টি কর্মসূচি',
  programme:'কর্মসূচি', health:'অবস্থা', delivered:'পরিকল্পনার বিপরীতে সরবরাহ', value:'প্রাপ্ত পণ্যের মূল্য', offices:'প্রাপ্ত কার্যালয়', codes:'ট্র্যাকিং কোডসমূহ',
  team:'দল', lastReceipt:'সর্বশেষ প্রাপ্তি', activeDraft:'সক্রিয় · খসড়া', expected:'প্রত্যাশিত',
  unavailable:'তথ্য অনুপলব্ধ', incomplete:'প্রাপ্তি অসম্পূর্ণ', crore:'কোটি', lakh:'লাখ',
  waiting:'{n}টি অপেক্ষায়', noAllocation:'বরাদ্দ নির্ধারিত নেই',
  h_on_pace:'প্রত্যাশিত গতিতে', h_behind:'পিছিয়ে', h_attention:'মনোযোগ প্রয়োজন', h_setup:'পরিকল্পনাধীন', h_closed:'বন্ধ', h_archived:'আর্কাইভকৃত',
  c_on_pace:'প্রত্যাশিত গতিতে', c_behind:'পিছিয়ে', c_stalled:'অগ্রগতি বন্ধ', c_over_allocation:'বরাদ্দের বেশি', c_complete:'সম্পূর্ণ',
  sev_critical:'অতি গুরুতর', sev_serious:'গুরুতর', sev_warning:'সতর্কতা',
  issues:'{n}টি সমস্যা', issue:'১টি সমস্যা', noIssues:'মনোযোগ দেওয়ার মতো কিছু নেই',
  r_HEAD:'অপারেশন প্রধান', r_OFFICER:'অপারেশন কর্মকর্তা', r_SUPPORT:'সহায়তা', r_OBSERVER:'পর্যবেক্ষক',
  next:'পরবর্তী পদক্ষেপ', contactAbout:'{what} সম্পর্কে {p} এর সাথে যোগাযোগ করুন।', overdue:'বকেয়া সরবরাহ', setupNext:'{p} সেটআপ সম্পন্ন করতে পারেন।', noHead:'এখনও কোনো অপারেশন প্রধান নির্ধারিত হয়নি।',
  monitor:'পর্যবেক্ষণ', manage:'পরিচালনা', report:'অগ্রগতি প্রতিবেদন', properties:'বৈশিষ্ট্য', open:'খুলুন',
  openMonitor:'পর্যবেক্ষণ খুলুন', continueSetup:'সেটআপ চালিয়ে যান', viewReport:'প্রতিবেদন দেখুন', openFull:'পূর্ণ পর্যবেক্ষণ খুলুন',
  setupOf:'সেটআপ {t}টির মধ্যে {d}টি', ready_team:'অপারেশন দল নির্ধারিত', ready_code:'প্রথম ট্র্যাকিং কোড তৈরি', ready_active:'একটি কোড সক্রিয় ও প্রাপ্তি গ্রহণ করছে',
  assignTeam:'দল নির্ধারণ', createCode:'কোড তৈরি', activateCode:'কোড সক্রিয় করুন',
  verdictWarn:'{n}টি সক্রিয় কর্মসূচিতে আপনার মনোযোগ প্রয়োজন', verdictWarn1:'১টি সক্রিয় কর্মসূচিতে আপনার মনোযোগ প্রয়োজন', verdictOk:'সব সক্রিয় কর্মসূচি প্রত্যাশিত গতিতে চলছে',
  startWith:'{t} দিয়ে শুরু করুন: {issue}', allClear:'এরপর: পরিকল্পনাধীন কর্মসূচিগুলো দেখুন।',
  k_operational:'সক্রিয় কর্মসূচি', k_value:'এ বছর প্রাপ্ত পণ্যের মূল্য', k_offices:'প্রাপ্ত কার্যালয়', k_codes:'ট্র্যাকিং কোডসমূহ',
  k_valueCap:'{g} পণ্য প্রাপ্তি নথি · {s} সরবরাহকারী', k_valueInc:'{n}টি কর্মসূচিতে অসম্পূর্ণ', k_officesCap:'{w}টি কার্যালয় এখনও অপেক্ষায়', k_codesCap:'সক্রিয় · খসড়া',
  mixOn:'{n}টি প্রত্যাশিত গতিতে', mixBehind:'{n}টি পিছিয়ে', mixAtt:'{n}টিতে মনোযোগ প্রয়োজন',
  ppBehind:'পরিকল্পনা থেকে {x} শতাংশ পয়েন্ট পিছিয়ে', ppAhead:'পরিকল্পনা থেকে {x} শতাংশ পয়েন্ট এগিয়ে', onPlan:'পরিকল্পনা অনুযায়ী', finalDelivery:'চূড়ান্ত সরবরাহ',
  daysAgo:'{n} দিন আগে', today:'আজ', yesterday:'গতকাল', monthsAgo:'{n} মাস আগে', never:'এখনও কোনো প্রাপ্তি নেই',
  updated:'হালনাগাদ {d}', closedOn:'বন্ধ {d}', archivedOn:'আর্কাইভ {d}',
  hello_m:'শুভ সকাল', hello_a:'শুভ অপরাহ্ন', hello_e:'শুভ সন্ধ্যা',
  helloLine:'{a}টি সক্রিয় · {b}টি পরিকল্পনাধীন · আজ {c}টিতে আপনার মনোযোগ প্রয়োজন',
  needsYou:'আপনার মনোযোগ প্রয়োজন', needsHint:'প্রতিটি সক্রিয় কর্মসূচির সবচেয়ে গুরুতর সমস্যা, যোগাযোগের ব্যক্তিসহ। সবচেয়ে গুরুতরগুলো আগে।', moreIssues:'এই কর্মসূচিতে আরও {n}টি',
  showAll:'সব {n}টি দেখুন', showFewer:'কম দেখান', operationalSec:'সক্রিয়', setupSec:'পরিকল্পনাধীন', setupHint:'এই কর্মসূচিগুলো এখনও প্রাপ্তি গ্রহণ করছে না। তালিকায় বাকি কাজ দেখানো হয়েছে।',
  closedSec:'বন্ধ ও আর্কাইভকৃত', youTeam:'আপনার দল', noTeam:'এখনও দল নেই', more:'+{n} জন',
  empty:'এই ফিল্টারে কোনো কর্মসূচি নেই।', clear:'ফিল্টার মুছুন',
  keyHint:'↑ ও ↓ দিয়ে কর্মসূচি বদলান', back:'সব কর্মসূচি', trend:'অর্থবছরে সরবরাহের অগ্রগতি', actual:'সরবরাহকৃত', expectedLine:'প্রত্যাশিত',
  noCodesYet:'এখনও কোনো ট্র্যাকিং কোড নেই', purposeMissing:'উদ্দেশ্য এখনও লেখা হয়নি',
  expand:'{t} এর বিস্তারিত দেখান', collapse:'{t} এর বিস্তারিত লুকান', sortBy:'{c} অনুযায়ী সাজান'
 }
};

/* ------------------------------------------------------------------ data */
function team(list){ return list.map(function(x){ var p=x.split('|'); return {name:p[0], role:p[1]}; }); }
var STALL_EN='No receipt progress recorded for more than 30 days.', STALL_BN='৩০ দিনের বেশি সময় কোনো প্রাপ্তির অগ্রগতি নথিভুক্ত হয়নি।';
var INC_EN='Some receipt links are incomplete. Quantities come from tracking records; value, date and supplier could not be confirmed.';
var INC_BN='কিছু প্রাপ্তি নথির সংযোগ অসম্পূর্ণ। ট্র্যাকিং রেকর্ড থেকে পরিমাণ দেখানো হয়েছে; সম্পূর্ণ মূল্য, তারিখ ও সরবরাহকারীর তথ্য নিশ্চিত করা যায়নি।';
function stall(code){ return {sev:'serious', code:code, en:code+' · Stalled', bn:code+' · অগ্রগতি বন্ধ', den:STALL_EN, dbn:STALL_BN}; }
function inc(code){ return {sev:'critical', code:code, en:code, bn:code, den:INC_EN, dbn:INC_BN}; }

var DOICT={bn:'তথ্য ও যোগাযোগ প্রযুক্তি অধিদপ্তর', en:'Department of ICT'};
var POLICE={bn:'বাংলাদেশ পুলিশ', en:'Bangladesh Police'};
var SEC={bn:'মাধ্যমিক শিক্ষা অধিদপ্তর', en:'Secondary Education Department'};
var AIS={bn:'কৃষি তথ্য সার্ভিস (এআইএস)', en:'Agriculture Information Service (AIS)'};
var DSS={bn:'সমাজসেবা অধিদপ্তর', en:'Department of Social Services'};
var LGD={bn:'স্থানীয় সরকার বিভাগ', en:'Local Government Division'};
var DGHS={bn:'স্বাস্থ্য অধিদপ্তর', en:'Directorate General of Health Services'};
var BBS={bn:'বাংলাদেশ পরিসংখ্যান ব্যুরো', en:'Bangladesh Bureau of Statistics'};

var ICTD = {id:4, title:'ICTD Lab', purpose:null, status:'Active', funding:'ADP', owner:DOICT, fy:'2026-2027',
  delivered:0.001, expected:0.230, value:null, valueComplete:false, grn:0, suppliers:0, reached:1, allocated:2, waitingOffices:2,
  active:5, draft:2, health:{stalled:5}, attention:[inc('ictd'), stall('ictd'), stall('ictd-20'), stall('ictd-200'), stall('ictd-201')],
  team:team(['kamrul islam|HEAD','mithun mitun|OFFICER','Biplab Chandra Sarker|OFFICER']), last:'2026-08-27', updated:'2026-08-03', trend:[0,0.001,0.001,0.001], mine:true, real:true};
var BUDGET = {id:1, title:'Budget tracking', purpose:null, status:'Closed', funding:'REVENUE', owner:AIS, fy:'2026-2027',
  delivered:0.058, expected:0.237, value:null, valueComplete:false, grn:3, suppliers:0, reached:1, allocated:0, waitingOffices:0,
  active:2, draft:0, health:{stalled:2}, attention:[inc('doict-gob-24'), stall('doict-ab-23'), stall('doict-gob-24')],
  team:[], last:'2026-07-29', updated:'2026-07-31', trend:[0.058,0.058,0.058,0.058], mine:false, real:true};

function exp(n, title, owner, teamList, updated){
  return {id:n, title:title, purpose:'পরীক্ষামূলক সরকারি সরঞ্জাম বিতরণ ও পরিবীক্ষণ', status:'Active', funding:'ADP', owner:{bn:owner.bn+' (পরীক্ষামূলক EXP-eH8E4np6DWmpcPRI)', en:owner.en+' (পরীক্ষামূলক EXP-eH8E4np6DWmpcPRI)'}, fy:'2026-2027',
    delivered:0.023, expected:0.052, value:10300000, valueComplete:true, grn:4, suppliers:0, reached:4, allocated:0, waitingOffices:0,
    active:4, draft:0, health:{on_pace:4}, attention:[], team:team(teamList), last:'2026-10-04', updated:updated||'2026-10-04', trend:[0,0,0.01,0.023], mine:false, real:true};
}
var LIVE = [
  exp(30,'EXP-eH8E4np6DWmpcPRI পুলিশ অফিস প্রযুক্তি আধুনিকায়ন 6',POLICE,['Sujit Kumar Das|HEAD','Ananya Roy|OFFICER','Subrata Chakraborty|SUPPORT','Purnima Sarkar|OBSERVER']),
  exp(29,'EXP-eH8E4np6DWmpcPRI শিক্ষা সহায়তা সরঞ্জাম 5',SEC,['Md. Imran Hossain|HEAD','Rifat Ara|OFFICER','Md. Abdullah Al Mamun|SUPPORT','Sanjida Islam|OBSERVER']),
  exp(28,'EXP-eH8E4np6DWmpcPRI সরকারি অফিস ডিজিটাল সক্ষমতা 4',DOICT,['Md. Ashraful Islam|HEAD','Shamima Nasrin|OFFICER','Md. Rezaul Karim|SUPPORT','Tasnim Tabassum|OBSERVER']),
  exp(27,'EXP-eH8E4np6DWmpcPRI পুলিশ অফিস প্রযুক্তি আধুনিকায়ন 3',POLICE,['Md. Tanvir Ahmed|HEAD','Fahmida Khatun|OFFICER','Abdur Rahman|SUPPORT','Rumana Yasmin|OBSERVER']),
  exp(26,'EXP-eH8E4np6DWmpcPRI শিক্ষা সহায়তা সরঞ্জাম 2',SEC,['Md. Shafiqul Alam|HEAD','Ayesha Siddika|OFFICER','Md. Mizanur Rahman|SUPPORT','Sumaiya Akter|OBSERVER']),
  exp(25,'EXP-eH8E4np6DWmpcPRI সরকারি অফিস ডিজিটাল সক্ষমতা 1',DOICT,['Md. Mahmudul Alam|HEAD','Sadia Rahman|OFFICER','Md. Nazmul Haque|SUPPORT','Jannatul Ferdous|OBSERVER']),
  ICTD,
  {id:3, title:'social welfare budget operation', purpose:null, status:'Planning', funding:'REVENUE', owner:DSS, fy:null, active:0, draft:0, health:{}, attention:[], team:[], updated:'2026-08-02', mine:false, real:true},
  {id:2, title:'Office Network', purpose:'test', status:'Planning', funding:'ADP', owner:DOICT, fy:null, active:0, draft:0, health:{}, attention:[], team:team(['kamrul islam|HEAD','abbas|OFFICER']), updated:'2026-08-02', mine:true, real:true},
  BUDGET
];

function ex(o){ o.example=true; return o; }
var EXAMPLE = [
  ICTD,
  ex({id:101, title:'Union Digital Centre support', purpose:'Laptops, printers and scanners for union digital centres in Naogaon.', status:'Active', funding:'ADP', owner:LGD, fy:'2026-2027',
    delivered:0.64, expected:0.58, value:34200000, valueComplete:true, grn:41, suppliers:6, reached:38, allocated:40, waitingOffices:2,
    active:6, draft:0, health:{on_pace:5, behind:1}, attention:[{sev:'warning', code:'UDC-03', en:'UDC-03 · Behind', bn:'UDC-03 · পিছিয়ে', den:'9 pp behind plan. Two union offices have not received scanners.', dbn:'পরিকল্পনা থেকে ৯ শতাংশ পয়েন্ট পিছিয়ে। দুটি ইউনিয়ন কার্যালয় স্ক্যানার পায়নি।'}],
    team:team(['Nasrin Akter|HEAD','Md. Habibur Rahman|OFFICER','Sharmin Sultana|SUPPORT']), last:'2026-10-16', updated:'2026-10-16', trend:[0.10,0.28,0.47,0.64], mine:true}),
  ex({id:28, title:'সরকারি অফিস ডিজিটাল সক্ষমতা', purpose:'উপজেলা কার্যালয়ে ডেস্কটপ, ইউপিএস ও নেটওয়ার্ক সরঞ্জাম সরবরাহ।', status:'Active', funding:'ADP', owner:DOICT, fy:'2026-2027',
    delivered:0.88, expected:0.80, value:56000000, valueComplete:true, grn:73, suppliers:9, reached:64, allocated:64, waitingOffices:0,
    active:8, draft:0, health:{on_pace:7, complete:1}, attention:[], team:team(['Md. Ashraful Islam|HEAD','Shamima Nasrin|OFFICER','Md. Rezaul Karim|SUPPORT','Tasnim Tabassum|OBSERVER']), last:'2026-10-18', updated:'2026-10-18', trend:[0.22,0.51,0.74,0.88], mine:false}),
  ex({id:29, title:'শিক্ষা সহায়তা সরঞ্জাম', purpose:'মাধ্যমিক বিদ্যালয়ে মাল্টিমিডিয়া প্রজেক্টর ও ল্যাপটপ।', status:'Active', funding:'ADP', owner:SEC, fy:'2026-2027',
    delivered:0.41, expected:0.52, value:21500000, valueComplete:true, grn:18, suppliers:4, reached:22, allocated:30, waitingOffices:8,
    active:4, draft:1, health:{on_pace:2, behind:2}, attention:[{sev:'serious', code:'SEC-02', en:'SEC-02 · Behind', bn:'SEC-02 · পিছিয়ে', den:'11 pp behind plan. The last receipt was 24 days ago.', dbn:'পরিকল্পনা থেকে ১১ শতাংশ পয়েন্ট পিছিয়ে। সর্বশেষ প্রাপ্তি ২৪ দিন আগে।'},{sev:'warning', code:'SEC-04', en:'SEC-04 · Waiting offices', bn:'SEC-04 · অপেক্ষমাণ কার্যালয়', den:'8 offices are still waiting for their first delivery.', dbn:'৮টি কার্যালয় এখনও প্রথম সরবরাহের অপেক্ষায়।'}],
    team:team(['Md. Imran Hossain|HEAD','Rifat Ara|OFFICER','Md. Abdullah Al Mamun|SUPPORT']), last:'2026-09-25', updated:'2026-10-10', trend:[0.08,0.21,0.35,0.41], mine:true}),
  ex({id:102, title:'Agricultural extension tablets', purpose:'Tablets for sub-assistant agriculture officers in Rangpur division.', status:'Active', funding:'REVENUE', owner:AIS, fy:'2026-2027',
    delivered:0.12, expected:0.35, value:8400000, valueComplete:false, grn:6, suppliers:2, reached:9, allocated:25, waitingOffices:16,
    active:3, draft:0, health:{stalled:2, over_allocation:1}, attention:[{sev:'critical', code:'AIS-07', en:'AIS-07 · Over allocation', bn:'AIS-07 · বরাদ্দের বেশি', den:'Rangpur Sadar received 120 tablets against an allocation of 100.', dbn:'রংপুর সদর ১০০টি বরাদ্দের বিপরীতে ১২০টি ট্যাবলেট পেয়েছে।'}, stall('AIS-05'), stall('AIS-06')],
    team:team(['Farhana Yasmin|HEAD','Md. Jahidul Islam|OFFICER']), last:'2026-08-30', updated:'2026-09-02', trend:[0.05,0.12,0.12,0.12], mine:false}),
  ex({id:30, title:'পুলিশ অফিস প্রযুক্তি আধুনিকায়ন', purpose:'থানা পর্যায়ে কম্পিউটার ও নেটওয়ার্ক সরঞ্জাম।', status:'Active', funding:'ADP', owner:POLICE, fy:'2026-2027',
    delivered:0.023, expected:0.052, value:10300000, valueComplete:true, grn:4, suppliers:1, reached:4, allocated:0, waitingOffices:0,
    active:4, draft:0, health:{on_pace:4}, attention:[], team:team(['Sujit Kumar Das|HEAD','Ananya Roy|OFFICER','Subrata Chakraborty|SUPPORT','Purnima Sarkar|OBSERVER']), last:'2026-10-04', updated:'2026-10-04', trend:[0,0,0.01,0.023], mine:false}),
  ex({id:103, title:'Climate-resilient school furniture', purpose:'Flood-safe benches and storage for coastal secondary schools.', status:'Planning', funding:'ADP', owner:SEC, fy:'2026-2027',
    active:0, draft:3, health:{}, attention:[], team:team(['Mahbuba Rahman|HEAD','Md. Kamal Hossain|OFFICER','Tahmina Akter|SUPPORT']), updated:'2026-10-14', mine:true}),
  ex({id:2, title:'Office Network', purpose:null, status:'Planning', funding:'ADP', owner:DOICT, fy:null, active:0, draft:0, health:{}, attention:[], team:team(['kamrul islam|HEAD','abbas|OFFICER']), updated:'2026-08-02', mine:true}),
  ex({id:3, title:'social welfare budget operation', purpose:null, status:'Planning', funding:'REVENUE', owner:DSS, fy:null, active:0, draft:0, health:{}, attention:[], team:[], updated:'2026-08-02', mine:false}),
  BUDGET,
  ex({id:104, title:'Hospital PC refresh 2025', purpose:'Desktop replacement in upazila health complexes.', status:'Closed', funding:'ADP', owner:DGHS, fy:'2025-2026',
    delivered:0.97, expected:1, value:29000000, valueComplete:true, grn:58, suppliers:5, reached:52, allocated:54, waitingOffices:2, active:5, draft:0, health:{complete:4, behind:1}, attention:[],
    team:team(['Dr. Sabina Yasmin|HEAD','Md. Rafiqul Islam|OFFICER']), last:'2026-06-24', updated:'2026-07-02', trend:[], mine:false}),
  ex({id:105, title:'Census tablets 2022', purpose:'Tablets for population and housing census enumerators.', status:'Archived', funding:'ADP', owner:BBS, fy:'2021-2022',
    delivered:1, expected:1, value:412000000, valueComplete:true, grn:212, suppliers:3, reached:492, allocated:492, waitingOffices:0, active:6, draft:0, health:{complete:6}, attention:[],
    team:team(['Md. Abul Kalam Azad|HEAD']), last:'2022-05-30', updated:'2025-06-30', trend:[], mine:false})
];

/* ------------------------------------------------------------------ state */
var st = {opt:'a', data:'example', lang:'en', tab:'all', q:'', fund:'all', mine:false, sort:{key:null, dir:1}, open:{}, needsAll:false, sel:null, cView:'list'};
try{ st.opt=localStorage.getItem('pf-opt')||st.opt; st.data=localStorage.getItem('pf-data')||st.data; st.lang=localStorage.getItem('pf-lang')||st.lang; }catch(e){}
var h0=(location.hash||'').replace('#',''); if(['a','b','c'].indexOf(h0)>=0) st.opt=h0;

/* ------------------------------------------------------------------ helpers */
function T(k, v){ var s=(S[st.lang][k]!==undefined?S[st.lang][k]:S.en[k])||k; if(v) for(var x in v) s=s.split('{'+x+'}').join(v[x]); return s; }
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
function owner(p){ return p.owner ? (st.lang==='bn'?p.owner.bn:p.owner.en) : ''; }
function money(v){ if(v==null) return T('unavailable'); if(v>=1e7) return '৳'+(v/1e7).toFixed(2)+' '+T('crore'); if(v>=1e5) return '৳'+(v/1e5).toFixed(2)+' '+T('lakh'); return '৳'+Math.round(v).toLocaleString('en-IN'); }
function pct(x){ return (x*100).toFixed(1)+'%'; }
function days(d){ return Math.round((TODAY-new Date(d+'T12:00:00'))/864e5); }
function ago(d){ if(!d) return T('never'); var n=days(d); if(n<=0) return T('today'); if(n===1) return T('yesterday'); if(n<60) return T('daysAgo',{n:n}); return T('monthsAgo',{n:Math.round(n/30.4)}); }
function initials(n){ return n.trim().split(/\s+/).filter(function(w){return !/^(md\.|dr\.)$/i.test(w);}).slice(0,2).map(function(w){return w.charAt(0).toUpperCase();}).join('')||'?'; }
function head(p){ return (p.team||[]).filter(function(m){return m.role==='HEAD';})[0]||null; }
function href(p, what){ var b=BASE+'/'+p.id; return what==='manage'?b+'/manage':what==='report'?b+'/report':what==='edit'?b+'/edit':b; }
function linkAttrs(p, what){ return (p.example && p.id>100) ? 'href="#" data-demo="1"' : 'href="'+href(p,what)+'" target="_blank" rel="noopener"'; }
function icon(name){ return '<i class="fas '+name+'" aria-hidden="true"></i>'; }
function badge(tone, ic, label, extra){ return '<span class="gs-badge'+(extra?' '+extra:'')+'" data-tone="'+tone+'">'+icon(ic+' gs-badge__icon')+'<span class="gs-badge__label">'+esc(label)+'</span></span>'; }
var STATUS = {Active:['status-ready','fa-circle-play'], Planning:['status-pending','fa-pen-ruler'], Closed:['doc-posted','fa-flag-checkered'], Archived:['status-archived','fa-box-archive']};
function statusBadge(p){ var s=STATUS[p.status]; return badge(s[0], s[1], T('st_'+p.status)); }
var CODE_H = {on_pace:['success','fa-check'], behind:['warning','fa-clock'], stalled:['danger','fa-pause'], over_allocation:['danger','fa-triangle-exclamation'], complete:['success','fa-check']};
var SEV = {critical:['danger','fa-circle-exclamation'], serious:['danger','fa-triangle-exclamation'], warning:['warning','fa-triangle-exclamation']};
function healthKey(p){
  if(p.status==='Planning') return 'setup'; if(p.status==='Archived') return 'archived'; if(p.status==='Closed') return 'closed';
  var sev=p.attention.map(function(a){return a.sev;});
  if(p.health.stalled||p.health.over_allocation||sev.indexOf('critical')>=0||sev.indexOf('serious')>=0) return 'attention';
  if(p.health.behind||sev.indexOf('warning')>=0) return 'behind';
  return 'on_pace';
}
var HB = {on_pace:['success','fa-check'], behind:['warning','fa-clock'], attention:['danger','fa-circle-exclamation'], setup:['status-pending','fa-pen-ruler'], closed:['doc-posted','fa-flag-checkered'], archived:['status-archived','fa-box-archive']};
function healthBadge(p){ var k=healthKey(p); return badge(HB[k][0], HB[k][1], T('h_'+k)); }
function issuesText(p){ var n=p.attention.length; return n===0?T('noIssues'):n===1?T('issue'):T('issues',{n:n}); }
function mix(p){ return Object.keys(p.health).filter(function(k){return p.health[k];}).map(function(k){ return '<span>'+badge(CODE_H[k][0], CODE_H[k][1], T('c_'+k))+' <b class="pf-num">'+p.health[k]+'</b></span>'; }).join(''); }
function paceWords(p){
  if(p.delivered==null||p.expected==null) return '';
  if(p.status==='Closed'||p.status==='Archived') return '<span class="pf-pace-words">'+T('finalDelivery')+'</span>';
  var d=(p.delivered-p.expected)*100, x=Math.abs(d).toFixed(1);
  if(Math.abs(d)<1) return '<span class="pf-pace-words" data-tone="success">'+T('onPlan')+'</span>';
  return '<span class="pf-pace-words" data-tone="'+(d<0?(d<-10?'danger':d<-5?'warning':'muted'):'success')+'">'+T(d<0?'ppBehind':'ppAhead',{x:x})+'</span>';
}
function pace(p, opts){
  opts=opts||{};
  if(p.delivered==null) return '<span class="pf-muted">—</span>';
  var a=Math.max(0,Math.min(100,p.delivered*100)), e=p.expected==null?null:Math.max(0,Math.min(100,p.expected*100));
  var cap=e==null?'':T('expected')+' '+e.toFixed(1)+'%';
  return '<div class="gs-pace"><div class="gs-pace__numbers"><strong class="pf-num">'+a.toFixed(1)+'%</strong>'+(opts.label?'<span>'+esc(opts.label)+'</span>':'')+'</div>'+
    '<div class="gs-pace__track" role="progressbar" aria-label="'+esc(T('delivered'))+'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+a.toFixed(1)+'"><span class="gs-pace__fill" style="width:'+a+'%"></span>'+(e==null?'':'<span class="gs-pace__expected" style="left:'+e+'%" title="'+esc(cap)+'"></span>')+'</div>'+
    '<small class="pf-num">'+cap+'</small>'+(opts.words?paceWords(p):'')+'</div>';
}
function valueCell(p){
  if(p.status==='Planning') return '<span class="pf-muted">—</span>';
  var v='<span class="pf-num">'+esc(money(p.value))+'</span>';
  if(!p.valueComplete) v+='<span class="pf-cellnote"><span class="pf-val-flag">'+icon('fa-circle-info')+' '+T('incomplete')+'</span></span>';
  return v;
}
function officesText(p){ if(p.status==='Planning') return '—'; return p.allocated>0 ? p.reached+' / '+p.allocated : String(p.reached); }
function avatars(p, max){
  max=max||4; var t=(p.team||[]).slice().sort(function(a,b){return (a.role==='HEAD'?0:1)-(b.role==='HEAD'?0:1);});
  if(!t.length) return '<span class="pf-subtle" style="font-size:12px">'+T('noTeam')+'</span>';
  return '<span class="pf-avatars">'+t.slice(0,max).map(function(m){ return '<span class="gs-avatar gs-avatar--sm gs-tone-primary"'+(m.role==='HEAD'?' data-head':'')+' title="'+esc(m.name+' · '+T('r_'+m.role))+'">'+esc(initials(m.name))+'</span>'; }).join('')+
    (t.length>max?'<span class="pf-avatars__more">'+T('more',{n:t.length-max})+'</span>':'')+'</span>';
}
function person(m, size){ return '<span class="pf-person"><span class="gs-avatar '+(size==='md'?'':'gs-avatar--sm ')+'gs-tone-primary" aria-hidden="true">'+esc(initials(m.name))+'</span><span><strong>'+esc(m.name)+'</strong><small>'+T('r_'+m.role)+'</small></span></span>'; }
function nextStep(p){
  var h=head(p);
  if(p.status==='Planning') return h ? T('setupNext',{p:h.name}) : T('noHead');
  if(!h) return T('noHead');
  return T('contactAbout',{p:h.name, what:T('overdue')});
}
function readinessItems(p){
  var hasTeam=(p.team||[]).length>0, hasCode=(p.active+p.draft)>0, hasActive=p.active>0;
  return [
    {label:T('ready_team'), done:hasTeam, action:T('assignTeam')},
    {label:T('ready_code'), done:hasCode, action:T('createCode')},
    {label:T('ready_active'), done:hasActive, action:T('activateCode')}
  ];
}
function readiness(p, compact){
  var items=readinessItems(p), d=items.filter(function(i){return i.done;}).length, firstOpen=items.filter(function(i){return !i.done;})[0];
  var bar='<div class="gs-progress gs-progress--sm gs-tone-'+(d===3?'success':'primary')+'"><div class="gs-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="3" aria-valuenow="'+d+'" aria-label="'+esc(T('setupOf',{d:d,t:3}))+'"><span class="gs-progress__bar" style="width:'+(d/3*100)+'%"></span></div></div>';
  if(compact) return '<div class="gs-pace"><div class="gs-pace__numbers"><strong>'+T('setupOf',{d:d,t:3})+'</strong></div>'+bar+'<small>'+(firstOpen?esc(firstOpen.label):'')+'</small></div>';
  return '<section class="gs-readiness"><div class="gs-readiness__head"><div class="gs-readiness__text"><strong class="gs-readiness__title">'+T('setupOf',{d:d,t:3})+'</strong></div></div>'+bar+
    '<ul class="gs-readiness__list">'+items.map(function(i){ return '<li class="gs-readiness__item'+(i.done?' gs-readiness__item--done':'')+'">'+icon((i.done?'fa-circle-check':'fa-circle')+' gs-readiness__icon')+'<span class="gs-readiness__label">'+esc(i.label)+'</span>'+(!i.done&&i===firstOpen?'<a class="gs-btn gs-btn--sm" '+linkAttrs(p,'manage')+'>'+esc(i.action)+'</a>':'')+'</li>'; }).join('')+'</ul></section>';
}
function attentionList(p, max){
  var items=p.attention.slice(0,max||p.attention.length);
  if(!items.length) return '<p class="pf-muted" style="margin:0">'+icon('fa-circle-check')+' '+T('noIssues')+'</p>';
  return '<ul class="gs-attention">'+items.map(function(a){ return attentionItem(p,a,false); }).join('')+'</ul>';
}
function attentionItem(p, a, withProg, rest){
  var tone=SEV[a.sev][0];
  return '<li class="gs-attention__item" data-tone="'+tone+'">'+icon('fa-circle-exclamation')+'<div>'+(withProg?'<span class="pf-needs__prog">'+esc(p.title)+'</span>':'')+'<small>'+T('sev_'+a.sev)+'</small><strong>'+esc(st.lang==='bn'?a.bn:a.en)+'</strong><p>'+esc(st.lang==='bn'?a.dbn:a.den)+'</p>'+(rest?'<p class="pf-subtle">'+T('moreIssues',{n:rest})+'</p>':'')+
    (withProg&&head(p)?'<span class="pf-needs__who"><span class="gs-avatar gs-avatar--sm gs-tone-primary" aria-hidden="true">'+esc(initials(head(p).name))+'</span>'+esc(head(p).name)+' · '+T('r_HEAD')+'</span>':'')+
    '</div><a '+linkAttrs(p)+'>'+T('open')+' <span class="pf-sr">'+esc(a.en)+'</span>'+icon('fa-arrow-right')+'</a></li>';
}
function levelLinks(p){
  var opts=[['monitor',''],['manage','manage'],['report','report']];
  return '<nav class="gs-level-switch" aria-label="'+esc(p.title)+'">'+opts.map(function(o,i){ return '<a class="gs-level-switch__option" '+linkAttrs(p,o[1])+(i===0?' aria-current="page"':'')+'>'+T(o[0])+'</a>'; }).join('')+'</nav>';
}

/* ------------------------------------------------------------------ dataset + filters */
function all(){ return st.data==='live'?LIVE:EXAMPLE; }
function matches(p){
  if(st.fund!=='all' && p.funding!==st.fund) return false;
  if(st.mine && !p.mine) return false;
  if(st.q){ var q=st.q.toLowerCase(); var hay=[p.title,p.owner&&p.owner.en,p.owner&&p.owner.bn,p.purpose].concat((p.team||[]).map(function(m){return m.name;})).join(' ').toLowerCase(); if(hay.indexOf(q)<0) return false; }
  return true;
}
function filtered(){ return all().filter(matches); }
function byTab(list){ return st.tab==='all'?list:list.filter(function(p){return p.status===st.tab;}); }
var RANK={attention:0, behind:1, on_pace:2, setup:3, closed:4, archived:5};
function defaultOrder(a,b){ var r=RANK[healthKey(a)]-RANK[healthKey(b)]; if(r) return r; return a.updated<b.updated?1:-1; }
var ORDER={critical:0, serious:1, warning:2};
function needs(list){
  var out=[];
  list.filter(function(p){return p.status==='Active'&&p.attention.length;}).forEach(function(p){
    var top=p.attention.slice().sort(function(x,y){return ORDER[x.sev]-ORDER[y.sev];});
    out.push({p:p, a:top[0], rest:top.length-1});
  });
  return out.sort(function(x,y){ return ORDER[x.a.sev]-ORDER[y.a.sev] || y.rest-x.rest; });
}
function needingProgrammes(list){ return list.filter(function(p){ return p.status==='Active' && (healthKey(p)==='attention'||healthKey(p)==='behind'); }); }

/* ------------------------------------------------------------------ shared blocks */
function pageHead(extra){
  return '<div class="pf-crumb"><a href="#">'+T('crumbHome')+'</a> / '+T('navProgrammes')+'</div>'+
  '<div class="pf-head"><div class="pf-head__text">'+(extra&&extra.eyebrow?'<p class="pf-eyebrow">'+extra.eyebrow+'</p>':'')+'<h1 class="pf-title">'+T('pageTitle')+'</h1>'+(extra&&extra.sub?extra.sub:'<p class="pf-sub">'+T('pageSub')+'</p>')+'</div>'+
  '<div class="pf-head__actions"><a class="gs-btn gs-btn--primary" href="'+BASE+'/create" target="_blank" rel="noopener">'+icon('fa-plus')+' '+T('newProgramme')+'</a></div></div>'+
  '<div class="pf-nav"><nav class="gs-level-switch" aria-label="Tracking"><a class="gs-level-switch__option" href="#" aria-current="page">'+T('navProgrammes')+'</a><a class="gs-level-switch__option" href="http://snipeit.local/gov-store/admin/tracking/offices" target="_blank" rel="noopener">'+T('navOffices')+'</a><a class="gs-level-switch__option" href="#" data-demo="1">'+T('navFunding')+'</a></nav></div>';
}
function verdict(list){
  var act=list.filter(function(p){return p.status==='Active';}), need=needingProgrammes(act), n=needs(list);
  var status=need.length?'warning':'success';
  var title=need.length? (need.length===1?T('verdictWarn1'):T('verdictWarn',{n:need.length})) : T('verdictOk');
  var nx=n.length? T('startWith',{t:n[0].p.title, issue:(st.lang==='bn'?n[0].a.bn:n[0].a.en)})+' '+(head(n[0].p)?T('contactAbout',{p:head(n[0].p).name, what:T('overdue')}):'') : T('allClear');
  return '<div class="gs-status-callout" data-status="'+status+'">'+badge(status, status==='warning'?'fa-triangle-exclamation':'fa-circle-check', status==='warning'?T('sev_warning'):T('h_on_pace'))+'<div><strong>'+esc(title)+'</strong><p><b>'+T('next')+':</b> '+esc(nx)+'</p></div></div>';
}
function kpis(list){
  var act=list.filter(function(p){return p.status==='Active';});
  var on=act.filter(function(p){return healthKey(p)==='on_pace';}).length, be=act.filter(function(p){return healthKey(p)==='behind';}).length, at=act.filter(function(p){return healthKey(p)==='attention';}).length;
  var val=0, inc=0, grn=0, sup=0, reached=0, alloc=0, waiting=0, a=0, d=0;
  act.forEach(function(p){ if(p.value!=null) val+=p.value; if(!p.valueComplete) inc++; grn+=p.grn||0; sup+=p.suppliers||0; reached+=p.reached||0; alloc+=p.allocated||0; waiting+=p.waitingOffices||0; });
  list.filter(function(p){return p.status==='Active'||p.status==='Planning';}).forEach(function(p){ a+=p.active||0; d+=p.draft||0; });
  var tot=Math.max(1,act.length);
  var mixbar='<div class="pf-mixbar" aria-hidden="true">'+(on?'<span data-tone="success" style="flex:'+on+'"></span>':'')+(be?'<span data-tone="warning" style="flex:'+be+'"></span>':'')+(at?'<span data-tone="danger" style="flex:'+at+'"></span>':'')+'</div>'+
    '<div class="pf-mixkey"><span data-tone="success">'+T('mixOn',{n:on})+'</span><span data-tone="warning">'+T('mixBehind',{n:be})+'</span><span data-tone="danger">'+T('mixAtt',{n:at})+'</span></div>';
  return '<div class="pf-kpis">'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('k_operational')+'</span><span class="gs-kpi__value pf-num">'+act.length+'</span>'+mixbar+'</span></div>'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('k_value')+'</span><span class="gs-kpi__value pf-num">'+esc(money(val))+'</span><span class="gs-kpi__caption">'+T('k_valueCap',{g:grn,s:sup})+'</span>'+(inc?'<span class="pf-val-flag">'+icon('fa-circle-info')+' '+T('k_valueInc',{n:inc})+'</span>':'')+'</span></div>'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('k_offices')+'</span><span class="gs-kpi__value pf-num">'+(alloc?reached+' / '+alloc:reached)+'</span>'+(alloc?'<span class="gs-kpi__progress" aria-hidden="true"><span style="width:'+Math.min(100,reached/alloc*100)+'%"></span></span>':'')+'<span class="gs-kpi__caption">'+T('k_officesCap',{w:waiting})+'</span></span></div>'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('k_codes')+'</span><span class="gs-kpi__value pf-num">'+a+' · '+d+'</span><span class="gs-kpi__caption">'+T('k_codesCap')+'</span></span></div>'+
  '</div>';
}
function tabs(list){
  var keys=['all','Active','Planning','Closed','Archived'];
  return '<nav class="gs-status-tabs" aria-label="'+T('health')+'"><ul class="gs-tabs">'+keys.map(function(k){ var c=k==='all'?list.length:list.filter(function(p){return p.status===k;}).length;
    return '<li class="gs-tabs__item"><a class="gs-tabs__link" href="#" data-tab="'+k+'"'+(st.tab===k?' aria-current="page"':'')+'><span>'+T('st_'+k)+'</span><span class="gs-tabs__count'+(c===0?' gs-tabs__count--zero':'')+'">'+c+'</span></a></li>'; }).join('')+'</ul></nav>';
}
function filtersBar(shown, total, withTabs){
  return '<div class="pf-filters" role="search">'+
    '<label class="pf-search"><span class="pf-sr">'+T('search')+'</span>'+icon('fa-magnifying-glass')+'<input type="search" id="pf-q" value="'+esc(st.q)+'" placeholder="'+esc(T('search'))+'"></label>'+
    '<label class="pf-sr" for="pf-fund">'+T('fundingAll')+'</label><select class="pf-select" id="pf-fund">'+[['all',T('fundingAll')],['ADP','ADP'],['REVENUE','REVENUE'],['OTHER','OTHER']].map(function(o){return '<option value="'+o[0]+'"'+(st.fund===o[0]?' selected':'')+'>'+o[1]+'</option>';}).join('')+'</select>'+
    '<label class="pf-toggle"><input type="checkbox" id="pf-mine"'+(st.mine?' checked':'')+'> '+T('mine')+'</label>'+
    '<span class="pf-count" aria-live="polite">'+T('showing',{n:shown,t:total})+'</span></div>';
}
function emptyState(){ return '<div class="pf-empty">'+icon('fa-filter-circle-xmark')+'<p>'+T('empty')+'</p><button type="button" class="gs-btn gs-btn--ghost" data-clear>'+T('clear')+'</button></div>'; }

/* ------------------------------------------------------------------ Option A */
var COLS=[['title','programme'],['health','health'],['delivered','delivered'],['value','value'],['offices','offices'],['codes','codes'],['team','team'],['last','lastReceipt']];
function sortVal(p,k){
  switch(k){ case 'title': return p.title.toLowerCase(); case 'health': return RANK[healthKey(p)]*100-p.attention.length; case 'delivered': return p.delivered==null?-1:(p.delivered-(p.expected||0));
    case 'value': return p.value==null?-1:p.value; case 'offices': return p.reached||0; case 'codes': return (p.active||0)+(p.draft||0); case 'team': return (p.team||[]).length; case 'last': return p.last||''; }
}
function renderA(){
  var base=filtered(), list=byTab(base).slice();
  if(st.sort.key){ list.sort(function(a,b){ var x=sortVal(a,st.sort.key), y=sortVal(b,st.sort.key); return (x>y?1:x<y?-1:0)*st.sort.dir; }); } else list.sort(defaultOrder);
  var rows=list.map(function(p){
    var open=!!st.open[p.id], quiet=p.status==='Closed'||p.status==='Archived';
    var r='<tr class="pf-row'+(quiet?' pf-row--quiet':'')+'" data-open="'+open+'">'+
      '<td><button type="button" class="pf-expand" data-toggle="'+p.id+'" aria-expanded="'+open+'" aria-controls="pf-d-'+p.id+'" aria-label="'+esc(T(open?'collapse':'expand',{t:p.title}))+'">'+icon(open?'fa-minus':'fa-plus')+'</button></td>'+
      '<td><div class="pf-id"><a class="pf-id__title" '+linkAttrs(p)+'>'+esc(p.title)+'</a><div class="pf-meta">'+(st.tab==='all'?statusBadge(p):'')+'<span class="pf-chip pf-chip--fund">'+p.funding+'</span><span class="pf-meta__owner" title="'+esc(owner(p))+'">'+esc(owner(p))+'</span>'+(p.fy?'<span class="pf-dot-sep pf-num">'+p.fy+'</span>':'')+(p.mine?'<span class="pf-chip pf-chip--you">'+T('youTeam')+'</span>':'')+'</div></div></td>'+
      '<td>'+healthBadge(p)+(p.status!=='Planning'?'<span class="pf-cellnote">'+issuesText(p)+'</span>':'')+'</td>'+
      '<td style="min-width:190px">'+(p.status==='Planning'?readiness(p,true):pace(p,{words:true}))+'</td>'+
      '<td class="pf-num">'+valueCell(p)+'</td>'+
      '<td class="pf-num">'+officesText(p)+(p.status==='Active'&&p.waitingOffices?'<span class="pf-cellnote">'+T('waiting',{n:p.waitingOffices})+'</span>':'')+'</td>'+
      '<td class="pf-num">'+(p.active+p.draft===0?'<span class="pf-subtle">0</span>':p.active+' · '+p.draft)+'<span class="pf-cellnote">'+T('activeDraft')+'</span></td>'+
      '<td>'+avatars(p,3)+'</td>'+
      '<td>'+(p.status==='Planning'?'<span class="pf-subtle">—</span>':'<span title="'+esc(p.last||'')+'">'+ago(p.last)+'</span><span class="pf-cellnote pf-num">'+(p.last||'')+'</span>')+'</td></tr>';
    var h=head(p);
    var detail='<tr class="pf-detail" id="pf-d-'+p.id+'"'+(open?'':' hidden')+'><td colspan="9"><div class="pf-detail__grid">'+
      '<div><h4>'+(p.status==='Planning'?T('setupSec'):T('needsYou'))+'</h4>'+(p.status==='Planning'?readiness(p,false):attentionList(p,3)+(p.attention.length>3?'<p style="margin:8px 0 0"><a class="pf-link" '+linkAttrs(p)+'>'+T('showAll',{n:p.attention.length})+' →</a></p>':''))+'</div>'+
      '<div class="pf-detail__next"><h4>'+T('next')+'</h4>'+(h?person(h,'md'):'')+'<p style="margin:0">'+esc(nextStep(p))+'</p>'+(p.status==='Active'&&Object.keys(p.health).length?'<div class="pf-mix">'+mix(p)+'</div>':'')+'</div>'+
      '<div><h4>'+T('open')+'</h4>'+levelLinks(p)+'<p class="pf-subtle" style="font-size:12px;margin:10px 0 0">'+T('updated',{d:ago(p.updated)})+'</p></div>'+
      '</div></td></tr>';
    return r+detail;
  }).join('');
  var head_='<thead><tr><th><span class="pf-sr">'+T('expand',{t:''})+'</span></th>'+COLS.map(function(c){ var s=st.sort.key===c[0]; var right=['value','offices','codes'].indexOf(c[0])>=0;
    return '<th scope="col"'+(right?' class="pf-r"':'')+(s?' aria-sort="'+(st.sort.dir>0?'ascending':'descending')+'"':'')+'><button type="button" data-sort="'+c[0]+'" '+(s?'aria-sort="1"':'')+' aria-label="'+esc(T('sortBy',{c:T(c[1])}))+'">'+T(c[1])+' '+icon(s?(st.sort.dir>0?'fa-arrow-up':'fa-arrow-down'):'fa-sort')+'</button></th>'; }).join('')+'</tr></thead>';
  var cards=list.map(cardB).join('');
  return pageHead()+
    '<div class="pf-band">'+verdict(base)+kpis(base)+'</div>'+
    '<div class="pf-ledger-tools">'+tabs(base)+'</div>'+
    '<div class="pf-panel"><div class="pf-panel__head">'+filtersBar(list.length, all().length)+'</div>'+
    (list.length? '<div class="pf-a-table gs-table-wrap"><table class="gs-table pf-ledger" data-density="regular">'+head_+'<tbody>'+rows+'</tbody></table></div><div class="pf-a-cards" style="padding:12px">'+cards+'</div>' : emptyState())+'</div>';
}

/* ------------------------------------------------------------------ Option B */
function cardB(p){
  if(p.status==='Planning'){
    return '<article class="pf-card pf-card--setup"><div class="pf-card__head">'+statusBadge(p)+'<span class="pf-chip pf-chip--fund">'+p.funding+'</span>'+(p.mine?'<span class="pf-chip pf-chip--you">'+T('youTeam')+'</span>':'')+'</div>'+
      '<h3 class="pf-card__title"><a '+linkAttrs(p)+'>'+esc(p.title)+'</a></h3><p class="pf-card__owner" title="'+esc(owner(p))+'">'+esc(owner(p))+'</p>'+
      readiness(p,false)+
      '<div class="pf-card__foot">'+(head(p)?person(head(p)):'<span class="pf-subtle" style="font-size:12.5px">'+T('noHead')+'</span>')+'<div class="pf-card__links"><a class="gs-btn gs-btn--primary gs-btn--sm" '+linkAttrs(p,'manage')+'>'+T('continueSetup')+'</a></div></div></article>';
  }
  var quiet=p.status!=='Active', a=p.attention[0];
  var issue = quiet ? '<div class="pf-card__issue pf-muted">'+icon('fa-flag-checkered')+'<p>'+(p.status==='Closed'?T('closedOn',{d:ago(p.updated)}):T('archivedOn',{d:ago(p.updated)}))+'</p></div>'
    : a ? '<div class="pf-card__issue">'+badge(SEV[a.sev][0], SEV[a.sev][1], T('sev_'+a.sev))+'<p><strong>'+esc(st.lang==='bn'?a.bn:a.en)+'</strong>'+(p.attention.length>1?'<small>'+T('more',{n:p.attention.length-1}).replace(/ জন$/,'টি')+'</small>':'')+'</p></div>'
    : '<div class="pf-card__issue pf-muted">'+icon('fa-circle-check')+'<p>'+T('noIssues')+'</p></div>';
  return '<article class="pf-card"><div class="pf-card__head">'+healthBadge(p)+'<span class="pf-chip pf-chip--fund">'+p.funding+'</span>'+(p.fy?'<span class="pf-subtle pf-num" style="font-size:12px">'+p.fy+'</span>':'')+(p.mine?'<span class="pf-chip pf-chip--you">'+T('youTeam')+'</span>':'')+'</div>'+
    '<h3 class="pf-card__title"><a '+linkAttrs(p)+'>'+esc(p.title)+'</a></h3><p class="pf-card__owner" title="'+esc(owner(p))+'">'+esc(owner(p))+'</p>'+
    pace(p,{label:T('delivered'), words:true})+
    '<dl class="pf-card__stats"><div><dt>'+T('value')+'</dt><dd title="'+esc(money(p.value))+'">'+esc(money(p.value))+'</dd></div><div><dt>'+T('offices')+'</dt><dd>'+officesText(p)+'</dd></div><div><dt>'+T('codes')+'</dt><dd>'+p.active+' · '+p.draft+'</dd></div></dl>'+
    issue+
    '<div class="pf-card__foot">'+(head(p)?person(head(p)):avatars(p))+'<div class="pf-card__links"><a class="gs-btn gs-btn--primary gs-btn--sm" '+linkAttrs(p)+'>'+T('openMonitor')+'</a><a class="gs-btn gs-btn--ghost gs-btn--sm" '+linkAttrs(p,'manage')+'>'+T('manage')+'</a></div></div></article>';
}
function greet(){ var h=new Date().getHours(); return T(h<12?'hello_m':h<17?'hello_a':'hello_e'); }
function renderB(){
  var list=filtered(), active=list.filter(function(p){return p.status==='Active';}).sort(defaultOrder), setup=list.filter(function(p){return p.status==='Planning';}),
      done=list.filter(function(p){return p.status==='Closed'||p.status==='Archived';}), n=needs(list), need=needingProgrammes(list);
  var shown=st.needsAll?n:n.slice(0,4);
  var sub='<p class="pf-b-hello"><span><strong>'+greet()+'.</strong></span><span>'+T('helloLine',{a:active.length,b:setup.length,c:need.length})+'</span></p>';
  var dateStr=TODAY.toLocaleDateString(st.lang==='bn'?'bn-BD':'en-GB',{weekday:'long', day:'numeric', month:'long', year:'numeric'});
  var h=pageHead({eyebrow:esc(dateStr), sub:sub});
  var needsHtml = n.length ? '<section class="pf-section"><div class="pf-section__head"><h2 class="pf-section__title">'+T('needsYou')+'</h2><span class="pf-section__count">'+n.length+'</span><span class="pf-section__hint">'+T('needsHint')+'</span></div>'+
     '<div class="pf-needs"><ul class="gs-attention">'+shown.map(function(x){return attentionItem(x.p,x.a,true,x.rest);}).join('')+'</ul></div>'+
     (n.length>4?'<p style="margin:10px 0 0"><button type="button" class="gs-btn gs-btn--ghost gs-btn--sm" data-needs>'+(st.needsAll?T('showFewer'):T('showAll',{n:n.length}))+'</button></p>':'')+'</section>' : '';
  var filters='<div class="pf-section">'+filtersBar(list.length, all().length)+'</div>';
  var sec=function(title,items,hint,body){ return items.length?'<section class="pf-section"><div class="pf-section__head"><h2 class="pf-section__title">'+title+'</h2><span class="pf-section__count">'+items.length+'</span>'+(hint?'<span class="pf-section__hint">'+hint+'</span>':'')+'</div>'+body+'</section>':''; };
  var quiet='<div class="pf-quiet">'+done.map(function(p){ return '<div class="pf-quiet__row"><div style="min-width:0"><strong>'+esc(p.title)+'</strong><div class="pf-meta"><span class="pf-meta__owner">'+esc(owner(p))+'</span>'+(p.fy?'<span class="pf-dot-sep pf-num">'+p.fy+'</span>':'')+'</div></div>'+statusBadge(p)+'<span class="pf-num">'+(p.delivered==null?'—':pct(p.delivered))+' <span class="pf-subtle" style="font-size:12px">'+T('finalDelivery')+'</span></span><span class="pf-num">'+esc(money(p.value))+'</span><a class="pf-link" '+linkAttrs(p,'report')+'>'+T('viewReport')+' →</a></div>'; }).join('')+'</div>';
  if(!list.length) return h+filters+emptyState();
  return h+needsHtml+filters+
    sec(T('operationalSec'), active, '', '<div class="pf-grid">'+active.map(cardB).join('')+'</div>')+
    sec(T('setupSec'), setup, T('setupHint'), '<div class="pf-grid">'+setup.map(cardB).join('')+'</div>')+
    sec(T('closedSec'), done, '', quiet);
}

/* ------------------------------------------------------------------ Option C */
function chart(p){
  var W=520,H=170,L=34,R=10,Tp=12,B=26, iw=W-L-R, ih=H-Tp-B, months=12;
  var x=function(m){return L+iw*m/months;}, y=function(v){return Tp+ih*(1-v);};
  var g=[0,.25,.5,.75,1].map(function(v){return '<line class="g" x1="'+L+'" x2="'+(W-R)+'" y1="'+y(v)+'" y2="'+y(v)+'"/><text x="'+(L-6)+'" y="'+(y(v)+3)+'" text-anchor="end">'+(v*100)+'%</text>';}).join('');
  var now=3.6, tr=p.trend||[], pts=tr.map(function(v,i){ return [x(Math.min(i+1,now)), y(v)]; }); pts.unshift([x(0),y(0)]);
  var path=pts.map(function(q,i){return (i?'L':'M')+q[0].toFixed(1)+','+q[1].toFixed(1);}).join(' ');
  var area=path+' L'+pts[pts.length-1][0].toFixed(1)+','+y(0)+' L'+x(0)+','+y(0)+' Z', last=pts[pts.length-1];
  var labels=st.lang==='bn'?['জুলাই','অক্টো','জানু','এপ্রি','জুন']:['Jul','Oct','Jan','Apr','Jun'];
  var lx=[0,3,6,9,12].map(function(m,i){return '<text x="'+x(m)+'" y="'+(H-8)+'" text-anchor="'+(i===0?'start':i===4?'end':'middle')+'">'+labels[i]+'</text>';}).join('');
  return '<svg class="pf-chart" viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T('trend'))+'">'+g+lx+
    '<line class="e" x1="'+x(0)+'" y1="'+y(0)+'" x2="'+x(12)+'" y2="'+y(1)+'"/>'+
    '<line class="t" x1="'+x(now)+'" x2="'+x(now)+'" y1="'+Tp+'" y2="'+y(0)+'"/>'+
    (tr.length?'<path class="ar" d="'+area+'"/><path class="a" d="'+path+'"/><circle cx="'+last[0]+'" cy="'+last[1]+'" r="4"/>':'')+'</svg>'+
    '<div class="pf-chart-legend"><span>━ '+T('actual')+'</span><span>┄ '+T('expectedLine')+'</span></div>';
}
function renderC(){
  var base=filtered(), list=byTab(base).slice().sort(defaultOrder);
  if(!list.some(function(p){return p.id===st.sel;})) st.sel=list.length?list[0].id:null;
  var groups=['Active','Planning','Closed','Archived'];
  var items=groups.map(function(g){ var gi=list.filter(function(p){return p.status===g;}); if(!gi.length) return '';
    return '<div class="pf-list__group" role="presentation">'+T('st_'+g)+' · '+gi.length+'</div>'+gi.map(function(p){
      var k=healthKey(p), sel=p.id===st.sel;
      var bar = p.status==='Planning' ? (function(){ var d=readinessItems(p).filter(function(i){return i.done;}).length; return '<span class="pf-li__bar"><span class="pf-mini"><span style="width:'+(d/3*100)+'%"></span></span><span>'+T('setupOf',{d:d,t:3})+'</span></span>'; })()
        : p.delivered==null?'' : '<span class="pf-li__bar"><span class="pf-mini"><span style="width:'+Math.min(100,p.delivered*100)+'%"></span>'+(p.expected!=null&&p.status==='Active'?'<i style="left:'+Math.min(100,p.expected*100)+'%"></i>':'')+'</span><span class="pf-num">'+pct(p.delivered)+'</span></span>';
      return '<button type="button" class="pf-li" role="option" aria-selected="'+sel+'" data-sel="'+p.id+'"><span class="pf-li__title">'+esc(p.title)+'</span>'+(p.status==='Active'?badge(HB[k][0],HB[k][1],k==='attention'?String(p.attention.length):T('h_'+k)):'')+
        '<span class="pf-li__sub">'+p.funding+' · '+esc(owner(p))+'</span>'+bar+'</button>'; }).join(''); }).join('');
  var chips='<div class="pf-chips" role="group" aria-label="'+T('health')+'">'+['all','Active','Planning','Closed','Archived'].map(function(k){ var c=k==='all'?base.length:base.filter(function(p){return p.status===k;}).length;
    return '<button type="button" data-tab="'+k+'" aria-pressed="'+(st.tab===k)+'">'+T('st_'+k)+'<b class="pf-num">'+c+'</b></button>'; }).join('')+'</div>';
  var left='<aside class="pf-list" aria-label="'+T('navProgrammes')+'"><div class="pf-list__tools"><label class="pf-search"><span class="pf-sr">'+T('search')+'</span>'+icon('fa-magnifying-glass')+'<input type="search" id="pf-q" value="'+esc(st.q)+'" placeholder="'+esc(T('search'))+'"></label>'+chips+
    '<label class="pf-toggle"><input type="checkbox" id="pf-mine"'+(st.mine?' checked':'')+'> '+T('mine')+'</label><span class="pf-subtle" style="font-size:12px">'+T('keyHint')+'</span></div>'+
    '<div class="pf-list__scroll" role="listbox" aria-label="'+T('navProgrammes')+'" id="pf-listbox">'+(items||emptyState())+'</div></aside>';
  var p=list.filter(function(x){return x.id===st.sel;})[0];
  var right='<section class="pf-preview" aria-live="polite">'+(p?previewC(p):emptyState())+'</section>';
  return pageHead()+'<div class="pf-split" data-view="'+st.cView+'">'+left+right+'</div>';
}
function previewC(p){
  var h=head(p);
  var top='<button type="button" class="gs-btn gs-btn--ghost gs-btn--sm pf-back" data-back>'+icon('fa-arrow-left')+' '+T('back')+'</button>'+
    '<div class="pf-preview__top"><div style="min-width:0;max-width:70ch"><h2 class="pf-preview__title">'+esc(p.title)+'</h2><p class="pf-preview__purpose">'+esc(p.purpose||T('purposeMissing'))+'</p></div>'+avatars(p,5)+'</div>'+
    '<div class="pf-meta">'+statusBadge(p)+'<span class="pf-chip pf-chip--fund">'+p.funding+'</span><span>'+esc(owner(p))+'</span>'+(p.fy?'<span class="pf-dot-sep pf-num">'+p.fy+'</span>':'')+'<span class="pf-dot-sep">'+(p.active+p.draft)+' '+T('codes')+'</span></div>'+levelLinks(p);
  if(p.status==='Planning'){
    return top+'<div class="gs-status-callout" data-status="warning">'+badge('status-pending','fa-pen-ruler',T('h_setup'))+'<div><strong>'+T('setupOf',{d:readinessItems(p).filter(function(i){return i.done;}).length,t:3})+'</strong><p><b>'+T('next')+':</b> '+esc(nextStep(p))+'</p></div></div>'+
      readiness(p,false)+'<div class="pf-preview__foot">'+(h?person(h,'md'):'<span></span>')+'<a class="gs-btn gs-btn--primary" '+linkAttrs(p,'manage')+'>'+T('continueSetup')+' '+icon('fa-arrow-right')+'</a></div>';
  }
  var a=p.attention[0], k=healthKey(p);
  var callout='<div class="gs-status-callout" data-status="'+(a?(a.sev==='warning'?'warning':'stalled'):'success')+'">'+healthBadge(p)+'<div><strong>'+esc(a?(st.lang==='bn'?a.bn:a.en):T('noIssues'))+'</strong><p><b>'+T('next')+':</b> '+esc(nextStep(p))+'</p></div></div>';
  var k4='<div class="pf-preview__kpis">'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('delivered')+'</span>'+pace(p,{words:true})+'</span></div>'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('value')+'</span><span class="gs-kpi__value pf-num">'+esc(money(p.value))+'</span><span class="gs-kpi__caption">'+T('k_valueCap',{g:p.grn||0,s:p.suppliers||0})+'</span></span></div>'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('offices')+'</span><span class="gs-kpi__value pf-num">'+officesText(p)+'</span><span class="gs-kpi__caption">'+(p.allocated?T('k_officesCap',{w:p.waitingOffices||0}):T('noAllocation'))+'</span></span></div>'+
    '<div class="gs-kpi"><span class="gs-kpi__main"><span class="gs-kpi__label">'+T('codes')+'</span><span class="gs-kpi__value pf-num">'+p.active+' · '+p.draft+'</span><span class="gs-kpi__caption">'+T('activeDraft')+'</span></span></div></div>';
  return top+callout+k4+'<div class="pf-mix">'+mix(p)+'</div>'+
    '<div class="pf-preview__two"><div><h3>'+T('needsYou')+'</h3>'+attentionList(p,3)+(p.attention.length>3?'<p style="margin:8px 0 0"><a class="pf-link" '+linkAttrs(p)+'>'+T('showAll',{n:p.attention.length})+' →</a></p>':'')+'</div>'+
    '<div><h3>'+T('trend')+' <span class="pf-subtle pf-num" style="font-weight:400">'+(p.fy||'')+'</span></h3>'+(p.status==='Active'?chart(p):'<p class="pf-muted">'+T('finalDelivery')+': '+(p.delivered==null?'—':pct(p.delivered))+'</p>')+'</div></div>'+
    '<div class="pf-preview__foot">'+(h?person(h,'md'):'<span></span>')+'<span class="pf-subtle" style="font-size:12px">'+T('lastReceipt')+': '+ago(p.last)+'</span><a class="gs-btn gs-btn--primary" '+linkAttrs(p)+'>'+T('openFull')+' '+icon('fa-arrow-right')+'</a></div>';
}

/* ------------------------------------------------------------------ notes */
var NOTES = {
 a:{sum:'About option A: Portfolio ledger', who:'Ministry and directorate staff who look after many programmes and compare them.', why:['Same column grammar as the codes table on the programme monitor, so opening a programme feels like zooming in','Sort by health, delivery gap, value or last receipt; expand a row for its issues, contact and links','One status callout and four portfolio KPIs answer “is anything wrong?” first'], trade:['Dense on small screens; switches to cards below 760 px','Needs per-programme monitor metrics on the index (see backend)'], kit:['page-header, level-switch, status-callout, kpi','status-tabs, table, pace-bar, status-badge','attention-list, readiness, avatar'], backend:['A lightweight portfolio summary per programme (delivered, expected, value, offices, codes, health, attention count). Running the full monitor for every row is too slow: building it for ten programmes did not finish within two minutes locally.']},
 b:{sum:'About option B: Programme cards', who:'Operation heads and officers with a few programmes who open the page to see what needs them today.', why:['Opens with a greeting and a “Needs you” list that names the issue, the programme and the person to contact','Each card is a small version of the monitor header: pace bar against expected, value, offices, codes','Programmes in setup show a checklist with the one next action; closed ones drop to a quiet list'], trade:['Less good for comparing many programmes side by side','The longest page of the three when the portfolio grows past ~20 programmes'], kit:['page-header, level-switch, attention-list','pace-bar, status-badge, readiness, progress','avatar, button'], backend:['Same portfolio summary as option A, plus a flat attention feed across operational programmes.','“Only programmes I work on” uses the operation-unit membership already used for authorisation.']},
 c:{sum:'About option C: List and preview', who:'Anyone triaging the portfolio quickly on a desktop: scan the list, read the preview, open the full monitor only when needed.', why:['The preview reuses the monitor’s own header, callout, KPIs, attention list and delivery chart, so the hand-off to the full page is seamless','Keyboard friendly: ↑ and ↓ move through programmes','On phones the list and preview become two steps'], trade:['Two panes need a wide screen to shine','The preview loads one programme at a time, which is cheap but adds a request per selection'], kit:['level-switch, status-callout, kpi, pace-bar','attention-list, readiness, status-badge, avatar','monitor chart pattern'], backend:['A summary endpoint for the list plus a preview fragment per programme (could be the monitor header partial rendered alone).','Selection kept in the URL (?programme=4) so a preview can be shared.']}
};
function renderNotes(){
  var n=NOTES[st.opt];
  document.getElementById('pf-notes-sum').textContent=n.sum;
  var ul=function(a){return '<ul>'+a.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>';};
  document.getElementById('pf-notes-body').innerHTML='<div><h4>Made for</h4><p>'+esc(n.who)+'</p></div><div><h4>Strengths</h4>'+ul(n.why)+'</div><div><h4>Trade-offs</h4>'+ul(n.trade)+'</div><div><h4>Kit components</h4>'+ul(n.kit)+'</div><div><h4>Backend needed</h4>'+ul(n.backend)+'</div>'+
    '<div><h4>Data</h4><p><span class="pf-datatag">'+(st.data==='live'?'Live: local database via snipeit.local, 19 Oct 2026':'Example: ICTD Lab and Budget tracking are real; other programmes are invented to show a busier portfolio')+'</span></p></div>';
}

/* ------------------------------------------------------------------ render + events */
var page=document.getElementById('pf-page');
function render(keepFocus){
  var f=document.activeElement, fid=f&&f.id, pos=f&&f.selectionStart;
  document.documentElement.setAttribute('lang', st.lang==='bn'?'bn':'en');
  page.innerHTML = st.opt==='a'?renderA():st.opt==='b'?renderB():renderC();
  [['pf-opt','opt'],['pf-data','data'],['pf-lang','lang']].forEach(function(x){ document.querySelectorAll('#'+x[0]+' button').forEach(function(b){ b.setAttribute('aria-pressed', String(b.dataset[x[1]]===st[x[1]])); }); });
  document.querySelectorAll('#pf-mode button').forEach(function(b){ b.setAttribute('aria-pressed', String(b.dataset.mode===document.documentElement.getAttribute('data-theme'))); });
  document.getElementById('pf-skin').value=document.documentElement.getAttribute('data-skin');
  renderNotes();
  if(keepFocus&&fid){ var el=document.getElementById(fid); if(el){ el.focus(); if(pos!=null&&el.setSelectionRange) try{el.setSelectionRange(pos,pos);}catch(e){} } }
}
function save(k,v){ try{ localStorage.setItem(k,v); }catch(e){} }
function toast(msg){ var t=document.getElementById('pf-toast'); t.textContent=msg; t.hidden=false; clearTimeout(toast._t); toast._t=setTimeout(function(){t.hidden=true;},2200); }

document.getElementById('pf-opt').addEventListener('click',function(e){ var b=e.target.closest('button'); if(!b) return; st.opt=b.dataset.opt; save('pf-opt',st.opt); history.replaceState(null,'','#'+st.opt); render(); });
document.getElementById('pf-data').addEventListener('click',function(e){ var b=e.target.closest('button'); if(!b) return; st.data=b.dataset.data; st.sel=null; save('pf-data',st.data); render(); });
document.getElementById('pf-lang').addEventListener('click',function(e){ var b=e.target.closest('button'); if(!b) return; st.lang=b.dataset.lang; save('pf-lang',st.lang); render(); });
document.getElementById('pf-mode').addEventListener('click',function(e){ var b=e.target.closest('button'); if(!b) return; document.documentElement.setAttribute('data-theme',b.dataset.mode); render(); });
document.getElementById('pf-skin').addEventListener('change',function(e){ document.documentElement.setAttribute('data-skin',e.target.value); save('pf-skin',e.target.value); });

page.addEventListener('click',function(e){
  var t;
  if((t=e.target.closest('[data-demo]'))){ e.preventDefault(); toast(st.lang==='bn'?'উদাহরণ কর্মসূচি: আসল অ্যাপে এটি সংশ্লিষ্ট পাতা খুলবে':'Example programme: in the app this opens the matching page'); return; }
  if((t=e.target.closest('[data-tab]'))){ e.preventDefault(); st.tab=t.dataset.tab; render(); return; }
  if((t=e.target.closest('[data-toggle]'))){ var id=t.dataset.toggle; st.open[id]=!st.open[id]; render(); var b=page.querySelector('[data-toggle="'+id+'"]'); if(b) b.focus(); return; }
  if((t=e.target.closest('[data-sort]'))){ var k=t.dataset.sort; if(st.sort.key===k){ if(st.sort.dir===1) st.sort.dir=-1; else st.sort={key:null,dir:1}; } else st.sort={key:k,dir:1}; render(); var s=page.querySelector('[data-sort="'+k+'"]'); if(s) s.focus(); return; }
  if((t=e.target.closest('[data-needs]'))){ st.needsAll=!st.needsAll; render(); return; }
  if((t=e.target.closest('[data-clear]'))){ st.q=''; st.fund='all'; st.mine=false; st.tab='all'; render(); return; }
  if((t=e.target.closest('[data-sel]'))){ st.sel=+t.dataset.sel; st.cView='preview'; render(); var s2=page.querySelector('[data-sel="'+st.sel+'"]'); if(s2&&window.innerWidth>860) s2.focus(); else window.scrollTo(0,0); return; }
  if((t=e.target.closest('[data-back]'))){ st.cView='list'; render(); return; }
});
page.addEventListener('input',function(e){ if(e.target.id==='pf-q'){ st.q=e.target.value; render(true); } });
page.addEventListener('change',function(e){ if(e.target.id==='pf-fund'){ st.fund=e.target.value; render(); } if(e.target.id==='pf-mine'){ st.mine=e.target.checked; render(true); } });
page.addEventListener('keydown',function(e){
  if(st.opt!=='c'||(e.key!=='ArrowDown'&&e.key!=='ArrowUp')) return;
  if(!e.target.closest('#pf-listbox')) return;
  var opts=[].slice.call(page.querySelectorAll('[data-sel]')), i=opts.findIndex(function(o){return +o.dataset.sel===st.sel;});
  var n=opts[Math.max(0,Math.min(opts.length-1,i+(e.key==='ArrowDown'?1:-1)))]; if(!n) return; e.preventDefault();
  st.sel=+n.dataset.sel; render(); var f=page.querySelector('[data-sel="'+st.sel+'"]'); if(f){ f.focus(); f.scrollIntoView({block:'nearest'}); }
});
window.addEventListener('hashchange',function(){ var h=(location.hash||'').replace('#',''); if(['a','b','c'].indexOf(h)>=0&&h!==st.opt){ st.opt=h; save('pf-opt',h); render(); } });
render();
})();
