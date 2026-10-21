(function(){
'use strict';
var NOW = '2026-10-21T12:00:00';  // prototype clock: session actions are stamped with this local time
var BASE = 'http://snipeit.local/gov-store/admin/tracking/initiatives';

/* ------------------------------------------------------------------ strings */
var S = {
 en: {
  crumbHome:'Tracking', crumbProgrammes:'Programmes', manageTitle:'Manage',
  tabMonitor:'Monitor', tabManage:'Manage', tabReport:'Progress report', tabOffices:'Offices',
  newCode:'New tracking code', settings:'Programme settings',
  st_Active:'Operational', st_Planning:'In setup', st_Closed:'Closed', st_Archived:'Archived',
  codesN:'{n} tracking codes', snapDelivered:'Delivered {p}', snapUnits:'{r} of {t} units', snapReceipts:'{n} receipts', snapLast:'last {d}', snapNone:'No receipts yet', openMonitor:'Open monitor',
  clWarn:'Needs attention', clOk:'In order',
  verdictOk:'Everything this programme needs is in place', verdictN:'{n} things to sort out before this programme is fully in order', verdict1:'1 thing to sort out before this programme is fully in order',
  nextHead:'You can resolve all of these.', nextOfficer:'You can resolve the setup items. Activating and archiving codes is for {p} (operation head).',
  nextRead:'Your role can view this page but not change it. Contact {p} (operation head).', nextNoHead:'No operation head is assigned yet.',
  todoTitle:'To do', todoNone:'Nothing waiting. New receipts appear on Monitor.',
  todoOrderMany:'Attach the order PDF to {n} active codes', todoOrderManyWhy:'This programme requires one per code. Storekeepers are already receiving against them.',
  todoOrder:'Attach the order PDF to {c}', todoOrderWhy:'Required for every code in this programme.',
  todoReady:'{c} is ready to activate', todoReadyWhy:'All setup checks pass. Activating locks its targets and lets storekeepers receive.',
  todoFinish:'Finish setting up {c}', todoFinishWhy:'Still missing: {m}.',
  todoTeam:'Assign an {r}', todoTeamWhy:'Needed before codes can be activated.',
  showCodes:'Show codes', attach:'Attach PDF', activate:'Activate', edit:'Edit', assign:'Assign',
  whoHead:'Operation head', whoEditors:'Head or officer',
  readyTitle:'Programme readiness', readyCaption:'What must be true for receipts to count.', readyCount:'{d} of {t} done',
  r_head:'Operation head assigned', r_officer:'At least one operation officer', r_active:'At least one code active', r_orders:'Order PDF on every active code',
  codesTitle:'Tracking codes', codesHint:'Each code is one order: what is planned, where it goes and who receives it.',
  f_all:'All', f_setup:'Needs setup', f_DRAFT:'Draft', f_ACTIVE:'Active', f_ARCHIVED:'Archived', search:'Search code, title or office',
  th_code:'Tracking code', th_plan:'Plan and scope', th_year:'Year and funding', th_checks:'Setup checks', th_status:'Status', th_actions:'Actions',
  products:'{n} products', unitsPlanned:'{n} units planned', noProducts:'No products yet',
  plan_1_BLANKET:'Blanket (no product targets)', plan_2_CATEGORY:'By product category', plan_3_MATRIX:'By office and product',
  oneOffice:'1 receiving office', nOffices:'{n} receiving offices', noScope:'Scope not set', allOffices:'All offices in area',
  c_products:'Products', c_scope:'Scope', c_order:'Order PDF',
  m_products:'products', m_scope:'geographic scope and receiving offices', m_order:'order PDF',
  s_DRAFT:'Draft', s_ACTIVE:'Active', s_ARCHIVED:'Archived',
  since:'Since {d}', createdOn:'Created {d}', archivedOn:'Archived {d}', receivedOf:'{r} of {t} received',
  more:'More', moreFor:'More actions for {c}',
  a_open:'Open code page', a_monitor:'Open in monitor', a_edit:'Edit properties', a_activate:'Activate and lock', a_attach:'Attach order PDF', a_replace:'Replace order PDF', a_archive:'Archive code', a_delete:'Delete draft',
  lockHead:'Only the operation head can do this', lockChecks:'Finish setup first: {m}', readOnly:'Read-only for your role', lockReceived:'Has receipts, so it can only be archived',
  expand:'Show plan for {c}', collapse:'Hide plan for {c}', details:'Details',
  d_products:'Planned products', d_product:'Product', d_econ:'Econ. code', d_planned:'Planned', d_received:'Received', d_total:'Total',
  d_blanket:'Blanket plan: any item received under this code counts. There are no product targets to show.',
  d_scope:'Scope', d_plan:'Plan type', d_geo:'Area', d_offices:'Receiving offices', d_fy:'Fiscal year', d_funding:'Funding',
  d_life:'Lifecycle', l_created:'Created', l_activated:'Activated and locked', l_first:'First receipt', l_archived:'Archived', l_pending:'Not yet',
  d_docs:'Order document', d_docsNone:'No order PDF attached.', d_docsReq:'This programme requires one before receipts are trusted.', d_docsOpt:'Optional for this programme.',
  d_change:'Change status', ar_activate:'Activate and lock', ar_activateWhy:'Locks targets, scope and fiscal year. Storekeepers at the receiving offices can then record receipts against it.',
  ar_archive:'Archive code', ar_archiveWhy:'Stops new receipts against this code. Its receipts and documents stay for audit.',
  ar_delete:'Delete draft', ar_deleteWhy:'Removes the draft and its targets. Only drafts with no receipts can be deleted.',
  ar_archivedRO:'Archived codes are read-only. Their history stays on Monitor and in the progress report.',
  teamTitle:'Operation team', teamManage:'Manage team', change:'Change', needed:'Needed', optional:'Optional',
  role_HEAD:'Operation head', role_OFFICER:'Operation officer', role_SUPPORT:'Support', role_MONITOR:'Monitor',
  roleDesc_HEAD:'Activates and archives codes, owns the team.', roleDesc_OFFICER:'Creates and edits codes, attaches documents.', roleDesc_SUPPORT:'Helps with records, links older receipts.', roleDesc_MONITOR:'Views progress and reports.',
  rulesTitle:'Programme rules', rule_docs:'Order PDF per code', rule_over:'Receipts above allocation', rule_meta:'Extra receipt details',
  yesRequired:'Required', notRequired:'Not required', blocked:'Blocked', allowedWarn:'Allowed with a warning',
  rulesWhy:'Set in programme settings. Changing a rule applies to new receipts only.', editRules:'Edit rules',
  toolsTitle:'More tools', t_retro:'Link earlier receipts', t_retroWhy:'Associate receipts recorded before a code existed.', t_report:'Progress report', t_reportWhy:'Printable report for the ministry.', t_props:'Programme properties', t_propsWhy:'Title, purpose, owner and funding.',
  open:'Open', activityTitle:'Activity', act_all:'All', act_setup:'Setup changes', act_receipts:'Receipts',
  ev_receipt:'received {u} units', ev_lines:'{n} product lines in one receipt', ev_activated:'activated and locked', ev_created:'created the programme', ev_codeCreated:'created draft', ev_doc:'attached order PDF to', ev_archived:'archived', ev_deleted:'deleted draft',
  ev_fromRecord:'From code record', unknownActor:'Not logged', showAllAct:'Show all {n}', showFewerAct:'Show fewer', noActivity:'No activity yet.',
  laneDraft:'Draft', laneDraftHint:'Being set up. The head activates a code when its checks pass.', laneActive:'Active', laneActiveHint:'Receiving. Targets and scope are locked.', laneArchived:'Archived', laneArchivedHint:'Closed to receipts and kept for audit.', laneEmpty:'No codes here.',
  close:'Close', cancel:'Cancel',
  dlg_activate:'Activate {c}?', dlg_activate1:'Targets, scope and fiscal year are locked.', dlg_activate2:'Storekeepers at {o} can record receipts against it.', dlg_activate3:'Later it can be archived, but not deleted.',
  dlg_archive:'Archive {c}?', dlg_archive1:'No new receipts can be recorded against it.', dlg_archive2:'Its {n} receipts and documents stay for audit.', dlg_archive3:'Only an administrator can reverse this.',
  dlg_delete:'Delete draft {c}?', dlg_delete1:'The draft and its product targets are removed.', dlg_delete2:'Nothing has been received against it.',
  dlg_attach:'Attach order PDF to {c}', dlg_attachFile:'Order (PDF, up to 10 MB)', dlg_attach1:'Storekeepers and auditors can open it from the code page.',
  toast_activated:'{c} is active. Storekeepers can now receive against it.', toast_archived:'{c} archived.', toast_deleted:'Draft {c} deleted.', toast_attached:'Order PDF attached to {c}.', toast_link:'In the real page this opens {w}.',
  emptyFilter:'No codes match.', clearFilter:'Show all codes', noCodes:'No tracking codes yet', noCodesHint:'Create the first code to start recording receipts.'
 },
 bn: {
  crumbHome:'ট্র্যাকিং', crumbProgrammes:'কর্মসূচি', manageTitle:'পরিচালনা',
  tabMonitor:'পর্যবেক্ষণ', tabManage:'পরিচালনা', tabReport:'অগ্রগতি প্রতিবেদন', tabOffices:'কার্যালয়সমূহ',
  newCode:'নতুন ট্র্যাকিং কোড', settings:'কর্মসূচির সেটিংস',
  st_Active:'সক্রিয়', st_Planning:'পরিকল্পনাধীন', st_Closed:'বন্ধ', st_Archived:'আর্কাইভকৃত',
  codesN:'{n}টি ট্র্যাকিং কোড', snapDelivered:'সরবরাহ {p}', snapUnits:'{t} এককের মধ্যে {r}', snapReceipts:'{n}টি প্রাপ্তি', snapLast:'সর্বশেষ {d}', snapNone:'এখনও কোনো প্রাপ্তি নেই', openMonitor:'পর্যবেক্ষণ খুলুন',
  clWarn:'মনোযোগ প্রয়োজন', clOk:'সব ঠিক আছে',
  verdictOk:'এই কর্মসূচির প্রয়োজনীয় সবকিছু ঠিক আছে', verdictN:'কর্মসূচিটি পুরোপুরি ঠিক করতে {n}টি বিষয় বাকি', verdict1:'কর্মসূচিটি পুরোপুরি ঠিক করতে ১টি বিষয় বাকি',
  nextHead:'আপনি এগুলোর সবই সমাধান করতে পারেন।', nextOfficer:'সেটআপের কাজগুলো আপনি করতে পারেন। কোড সক্রিয় ও আর্কাইভ করবেন {p} (অপারেশন প্রধান)।',
  nextRead:'আপনার ভূমিকায় এই পাতা দেখা যায়, পরিবর্তন করা যায় না। {p} (অপারেশন প্রধান) এর সাথে যোগাযোগ করুন।', nextNoHead:'এখনও কোনো অপারেশন প্রধান নির্ধারিত হয়নি।',
  todoTitle:'করণীয়', todoNone:'অপেক্ষমাণ কিছু নেই। নতুন প্রাপ্তি পর্যবেক্ষণে দেখা যাবে।',
  todoOrderMany:'{n}টি সক্রিয় কোডে ক্রয়াদেশের পিডিএফ সংযুক্ত করুন', todoOrderManyWhy:'এই কর্মসূচিতে প্রতিটি কোডে একটি আবশ্যক। স্টোরকিপাররা ইতিমধ্যে প্রাপ্তি গ্রহণ করছেন।',
  todoOrder:'{c} এ ক্রয়াদেশের পিডিএফ সংযুক্ত করুন', todoOrderWhy:'এই কর্মসূচির প্রতিটি কোডে আবশ্যক।',
  todoReady:'{c} সক্রিয় করার জন্য প্রস্তুত', todoReadyWhy:'সব সেটআপ যাচাই সম্পন্ন। সক্রিয় করলে লক্ষ্যমাত্রা লক হবে এবং স্টোরকিপাররা প্রাপ্তি গ্রহণ করতে পারবেন।',
  todoFinish:'{c} এর সেটআপ সম্পন্ন করুন', todoFinishWhy:'এখনও বাকি: {m}।',
  todoTeam:'{r} নির্ধারণ করুন', todoTeamWhy:'কোড সক্রিয় করার আগে প্রয়োজন।',
  showCodes:'কোড দেখুন', attach:'পিডিএফ সংযুক্ত', activate:'সক্রিয় করুন', edit:'সম্পাদনা', assign:'নির্ধারণ',
  whoHead:'অপারেশন প্রধান', whoEditors:'প্রধান বা কর্মকর্তা',
  readyTitle:'কর্মসূচির প্রস্তুতি', readyCaption:'প্রাপ্তি গণনার জন্য যা যা সঠিক থাকতে হবে।', readyCount:'{t}টির মধ্যে {d}টি সম্পন্ন',
  r_head:'অপারেশন প্রধান নির্ধারিত', r_officer:'অন্তত একজন অপারেশন কর্মকর্তা', r_active:'অন্তত একটি কোড সক্রিয়', r_orders:'প্রতিটি সক্রিয় কোডে ক্রয়াদেশের পিডিএফ',
  codesTitle:'ট্র্যাকিং কোডসমূহ', codesHint:'প্রতিটি কোড একটি ক্রয়াদেশ: কী পরিকল্পিত, কোথায় যাবে এবং কে গ্রহণ করবে।',
  f_all:'সব', f_setup:'সেটআপ বাকি', f_DRAFT:'খসড়া', f_ACTIVE:'সক্রিয়', f_ARCHIVED:'আর্কাইভকৃত', search:'কোড, শিরোনাম বা কার্যালয় খুঁজুন',
  th_code:'ট্র্যাকিং কোড', th_plan:'পরিকল্পনা ও পরিসর', th_year:'অর্থবছর ও অর্থায়ন', th_checks:'সেটআপ যাচাই', th_status:'অবস্থা', th_actions:'কার্যক্রম',
  products:'{n}টি পণ্য', unitsPlanned:'{n} একক পরিকল্পিত', noProducts:'এখনও কোনো পণ্য নেই',
  plan_1_BLANKET:'সাধারণ (পণ্যভিত্তিক লক্ষ্যমাত্রা নেই)', plan_2_CATEGORY:'পণ্যের ধরন অনুযায়ী', plan_3_MATRIX:'কার্যালয় ও পণ্য অনুযায়ী',
  oneOffice:'১টি গ্রহণকারী কার্যালয়', nOffices:'{n}টি গ্রহণকারী কার্যালয়', noScope:'পরিসর নির্ধারিত হয়নি', allOffices:'এলাকার সব কার্যালয়',
  c_products:'পণ্য', c_scope:'পরিসর', c_order:'ক্রয়াদেশ',
  m_products:'পণ্য', m_scope:'ভৌগোলিক পরিসর ও গ্রহণকারী কার্যালয়', m_order:'ক্রয়াদেশের পিডিএফ',
  s_DRAFT:'খসড়া', s_ACTIVE:'সক্রিয়', s_ARCHIVED:'আর্কাইভকৃত',
  since:'{d} থেকে', createdOn:'তৈরি {d}', archivedOn:'আর্কাইভ {d}', receivedOf:'{t} এর মধ্যে {r} প্রাপ্ত',
  more:'আরও', moreFor:'{c} এর আরও কার্যক্রম',
  a_open:'কোডের পাতা খুলুন', a_monitor:'পর্যবেক্ষণে খুলুন', a_edit:'বৈশিষ্ট্য সম্পাদনা', a_activate:'সক্রিয় ও লক করুন', a_attach:'ক্রয়াদেশের পিডিএফ সংযুক্ত করুন', a_replace:'ক্রয়াদেশের পিডিএফ বদলান', a_archive:'কোড আর্কাইভ করুন', a_delete:'খসড়া মুছুন',
  lockHead:'শুধু অপারেশন প্রধান এটি করতে পারেন', lockChecks:'আগে সেটআপ সম্পন্ন করুন: {m}', readOnly:'আপনার ভূমিকায় শুধু দেখা যায়', lockReceived:'প্রাপ্তি আছে, তাই শুধু আর্কাইভ করা যাবে',
  expand:'{c} এর পরিকল্পনা দেখান', collapse:'{c} এর পরিকল্পনা লুকান', details:'বিস্তারিত',
  d_products:'পরিকল্পিত পণ্য', d_product:'পণ্য', d_econ:'অর্থনৈতিক কোড', d_planned:'পরিকল্পিত', d_received:'প্রাপ্ত', d_total:'মোট',
  d_blanket:'সাধারণ পরিকল্পনা: এই কোডে প্রাপ্ত যেকোনো পণ্য গণনা হয়। দেখানোর মতো পণ্যভিত্তিক লক্ষ্যমাত্রা নেই।',
  d_scope:'পরিসর', d_plan:'পরিকল্পনার ধরন', d_geo:'এলাকা', d_offices:'গ্রহণকারী কার্যালয়', d_fy:'অর্থবছর', d_funding:'অর্থায়ন',
  d_life:'জীবনচক্র', l_created:'তৈরি', l_activated:'সক্রিয় ও লক', l_first:'প্রথম প্রাপ্তি', l_archived:'আর্কাইভ', l_pending:'এখনও নয়',
  d_docs:'ক্রয়াদেশের নথি', d_docsNone:'কোনো ক্রয়াদেশের পিডিএফ সংযুক্ত নেই।', d_docsReq:'প্রাপ্তি নির্ভরযোগ্য ধরার আগে এই কর্মসূচিতে এটি আবশ্যক।', d_docsOpt:'এই কর্মসূচিতে ঐচ্ছিক।',
  d_change:'অবস্থা পরিবর্তন', ar_activate:'সক্রিয় ও লক করুন', ar_activateWhy:'লক্ষ্যমাত্রা, পরিসর ও অর্থবছর লক হবে। এরপর গ্রহণকারী কার্যালয়ের স্টোরকিপাররা এই কোডে প্রাপ্তি লিপিবদ্ধ করতে পারবেন।',
  ar_archive:'কোড আর্কাইভ করুন', ar_archiveWhy:'এই কোডে নতুন প্রাপ্তি বন্ধ হবে। প্রাপ্তি ও নথি নিরীক্ষার জন্য সংরক্ষিত থাকবে।',
  ar_delete:'খসড়া মুছুন', ar_deleteWhy:'খসড়া ও এর লক্ষ্যমাত্রা মুছে যাবে। শুধু প্রাপ্তিহীন খসড়া মোছা যায়।',
  ar_archivedRO:'আর্কাইভকৃত কোড শুধু দেখা যায়। এর ইতিহাস পর্যবেক্ষণ ও অগ্রগতি প্রতিবেদনে থাকে।',
  teamTitle:'অপারেশন দল', teamManage:'দল ব্যবস্থাপনা', change:'পরিবর্তন', needed:'প্রয়োজন', optional:'ঐচ্ছিক',
  role_HEAD:'অপারেশন প্রধান', role_OFFICER:'অপারেশন কর্মকর্তা', role_SUPPORT:'সহায়তা', role_MONITOR:'পর্যবেক্ষক',
  roleDesc_HEAD:'কোড সক্রিয় ও আর্কাইভ করেন, দলের দায়িত্বে।', roleDesc_OFFICER:'কোড তৈরি ও সম্পাদনা করেন, নথি সংযুক্ত করেন।', roleDesc_SUPPORT:'রেকর্ডে সহায়তা করেন, পুরোনো প্রাপ্তি যুক্ত করেন।', roleDesc_MONITOR:'অগ্রগতি ও প্রতিবেদন দেখেন।',
  rulesTitle:'কর্মসূচির নিয়ম', rule_docs:'প্রতি কোডে ক্রয়াদেশের পিডিএফ', rule_over:'বরাদ্দের বেশি প্রাপ্তি', rule_meta:'প্রাপ্তির অতিরিক্ত তথ্য',
  yesRequired:'আবশ্যক', notRequired:'আবশ্যক নয়', blocked:'অনুমোদিত নয়', allowedWarn:'সতর্কতাসহ অনুমোদিত',
  rulesWhy:'কর্মসূচির সেটিংসে নির্ধারিত। নিয়ম বদলালে শুধু নতুন প্রাপ্তিতে প্রযোজ্য।', editRules:'নিয়ম সম্পাদনা',
  toolsTitle:'আরও সরঞ্জাম', t_retro:'পূর্বের প্রাপ্তি যুক্ত করুন', t_retroWhy:'কোড তৈরির আগে লিপিবদ্ধ প্রাপ্তি সংযুক্ত করুন।', t_report:'অগ্রগতি প্রতিবেদন', t_reportWhy:'মন্ত্রণালয়ের জন্য মুদ্রণযোগ্য প্রতিবেদন।', t_props:'কর্মসূচির বৈশিষ্ট্য', t_propsWhy:'শিরোনাম, উদ্দেশ্য, মালিক ও অর্থায়ন।',
  open:'খুলুন', activityTitle:'কার্যক্রমের ইতিহাস', act_all:'সব', act_setup:'সেটআপ পরিবর্তন', act_receipts:'প্রাপ্তি',
  ev_receipt:'{u} একক গ্রহণ করেছেন', ev_lines:'এক প্রাপ্তিতে {n}টি পণ্যের লাইন', ev_activated:'সক্রিয় ও লক হয়েছে', ev_created:'কর্মসূচি তৈরি করেছেন', ev_codeCreated:'খসড়া তৈরি করেছেন', ev_doc:'ক্রয়াদেশের পিডিএফ সংযুক্ত করেছেন', ev_archived:'আর্কাইভ করেছেন', ev_deleted:'খসড়া মুছেছেন',
  ev_fromRecord:'কোডের রেকর্ড থেকে', unknownActor:'লগ করা হয়নি', showAllAct:'সব {n}টি দেখুন', showFewerAct:'কম দেখান', noActivity:'এখনও কোনো কার্যক্রম নেই।',
  laneDraft:'খসড়া', laneDraftHint:'সেটআপ চলছে। যাচাই সম্পন্ন হলে প্রধান কোড সক্রিয় করেন।', laneActive:'সক্রিয়', laneActiveHint:'প্রাপ্তি গ্রহণ চলছে। লক্ষ্যমাত্রা ও পরিসর লক।', laneArchived:'আর্কাইভকৃত', laneArchivedHint:'প্রাপ্তি বন্ধ, নিরীক্ষার জন্য সংরক্ষিত।', laneEmpty:'এখানে কোনো কোড নেই।',
  close:'বন্ধ করুন', cancel:'বাতিল',
  dlg_activate:'{c} সক্রিয় করবেন?', dlg_activate1:'লক্ষ্যমাত্রা, পরিসর ও অর্থবছর লক হবে।', dlg_activate2:'{o} এর স্টোরকিপাররা এতে প্রাপ্তি লিপিবদ্ধ করতে পারবেন।', dlg_activate3:'পরে আর্কাইভ করা যাবে, মোছা যাবে না।',
  dlg_archive:'{c} আর্কাইভ করবেন?', dlg_archive1:'এতে আর কোনো নতুন প্রাপ্তি লিপিবদ্ধ করা যাবে না।', dlg_archive2:'এর {n}টি প্রাপ্তি ও নথি নিরীক্ষার জন্য থাকবে।', dlg_archive3:'শুধু প্রশাসক এটি ফেরাতে পারেন।',
  dlg_delete:'খসড়া {c} মুছবেন?', dlg_delete1:'খসড়া ও এর পণ্যের লক্ষ্যমাত্রা মুছে যাবে।', dlg_delete2:'এতে কোনো প্রাপ্তি লিপিবদ্ধ হয়নি।',
  dlg_attach:'{c} এ ক্রয়াদেশের পিডিএফ সংযুক্ত করুন', dlg_attachFile:'ক্রয়াদেশ (পিডিএফ, সর্বোচ্চ ১০ এমবি)', dlg_attach1:'স্টোরকিপার ও নিরীক্ষকরা কোডের পাতা থেকে এটি খুলতে পারবেন।',
  toast_activated:'{c} সক্রিয়। স্টোরকিপাররা এখন প্রাপ্তি গ্রহণ করতে পারবেন।', toast_archived:'{c} আর্কাইভ হয়েছে।', toast_deleted:'খসড়া {c} মুছে ফেলা হয়েছে।', toast_attached:'{c} এ ক্রয়াদেশের পিডিএফ সংযুক্ত হয়েছে।', toast_link:'আসল পাতায় এটি {w} খুলবে।',
  emptyFilter:'কোনো কোড মেলেনি।', clearFilter:'সব কোড দেখান', noCodes:'এখনও কোনো ট্র্যাকিং কোড নেই', noCodesHint:'প্রাপ্তি লিপিবদ্ধ শুরু করতে প্রথম কোড তৈরি করুন।'
 }
};

/* ------------------------------------------------------------------ data */
var FX = 'EXP-eH8E4np6DWmpcPRI';
var LIVE_PRODUCTS = [['Desktop Computers / ডেস্কটপ কম্পিউটার 1',5],['Printers / প্রিন্টার 11',5],['Office Chairs / অফিস চেয়ার 16',5],['Office Desks / অফিস ডেস্ক 21',5],['Steel Cabinets / স্টিল আলমারি 26',5],['Scanners / স্ক্যানার 31',5],['UPS / ইউপিএস 36',5],['Network Routers / নেটওয়ার্ক রাউটার 41',4],['Projectors / প্রজেক্টর 46',4],['Air Conditioners / শীতাতপ নিয়ন্ত্রক 51',4],['Ceiling Fans / সিলিং ফ্যান 56',4],['Whiteboards / হোয়াইটবোর্ড 61',4]];
function liveCode(id,n,geo,office,grn,by,at){
  return {id:id, code:FX+'-ADP-'+n, title:'অফিস সরঞ্জাম বরাদ্দ / Office Equipment Allocation '+n, status:'ACTIVE', plan:'2_CATEGORY', fy:'2026-2027',
    funding:FX+' GoB (Taka) / সরকারি অর্থায়ন', geo:geo, offices:[office],
    products:LIVE_PRODUCTS.map(function(p){ return {name:FX+' '+p[0], econ:'SAMPLE-OFFICE', planned:200, received:p[1]}; }),
    docs:[], created:'2026-10-04T08:01:56', activated:'2026-10-04T08:01:58', archived:null, first:at, last:at, grns:1,
    _receipt:{grn:FX+'-CHALLAN-GR-2026-'+grn, by:by, at:at}};
}
var DATASETS = {
 live: function(){
  var codes = [
    liveCode(98,6,'Dhaka / ঢাকা','আঞ্চলিক কার্যালয় / Dhaka '+FX+'-6','000055','Rifat','2026-10-04T08:02:17'),
    liveCode(104,12,'Patuakhali District / পটুয়াখালী জেলা','জেলা কার্যালয় / Patuakhali District '+FX+'-12','000073','Purnima','2026-10-04T08:02:29'),
    liveCode(110,18,'Bogura District / বগুড়া জেলা','জেলা কার্যালয় / Bogura District '+FX+'-18','000091','Farzana Akter','2026-10-04T08:02:42'),
    liveCode(116,24,'Jamalpur District / জামালপুর জেলা','জেলা কার্যালয় / Jamalpur District '+FX+'-24','000109','Mst. Roksana Parvin','2026-10-04T08:02:55')
  ];
  var activity = [{type:'created', at:'2026-10-04T08:01:56', by:null, target:null, derived:true}];
  codes.forEach(function(c){
    activity.push({type:'activated', at:c.activated, by:null, target:c.code, derived:true});
    activity.push({type:'receipt', at:c._receipt.at, by:c._receipt.by, target:c.code, grn:c._receipt.grn, units:55, lines:12});
  });
  return {
    id:30, live:true, title:FX+' পুলিশ অফিস প্রযুক্তি আধুনিকায়ন 6', purpose:'পরীক্ষামূলক সরকারি সরঞ্জাম বিতরণ ও পরিবীক্ষণ',
    status:'Active', funding:'ADP', owner:'বাংলাদেশ পুলিশ / Bangladesh Police (পরীক্ষামূলক '+FX+')', created:'2026-10-04T08:01:56',
    rules:{docs:true, overshoot:false, metadata:false},
    team:[{d:'HEAD',n:'Sujit Kumar Das',j:'Office Administrator'},{d:'OFFICER',n:'Ananya Roy',j:'Office Administrator'},{d:'SUPPORT',n:'Subrata Chakraborty',j:'Office Administrator'},{d:'MONITOR',n:'Purnima Sarkar',j:'Office Administrator'}],
    delivered:2.29, receipts:4, codes:codes, activity:activity
  };
 },
 example: function(){
  var P='DGHS-DHD-', G='GoB (Taka) / সরকারি অর্থায়ন';
  function prods(list){ return list.map(function(p){ return {name:p[0], econ:p[1], planned:p[2], received:p[3]}; }); }
  var codes = [
   {id:201, code:P+'ADP-01', title:'জেলা হাসপাতালে ডেস্কটপ কম্পিউটার, পর্যায় ১ / Desktop computers for district hospitals, phase 1', status:'ACTIVE', plan:'2_CATEGORY', fy:'2026-2027', funding:G,
    geo:'Dhaka Division / ঢাকা বিভাগ', offices:new Array(18).fill('x').map(function(_,i){return 'District hospital '+(i+1);}),
    products:prods([['Desktop computers / ডেস্কটপ কম্পিউটার','3257105',320,214],['UPS / ইউপিএস','3257105',320,198],['Laser printers / লেজার প্রিন্টার','3257106',128,77]]),
    docs:[{name:'DGHS-ORD-2026-114.pdf', at:'2026-07-20T10:12:00', by:'Tanvir Hossain'}], created:'2026-07-16T09:00:00', activated:'2026-07-21T11:30:00', archived:null, first:'2026-08-10T12:00:00', last:'2026-10-15T15:20:00', grns:23},
   {id:202, code:P+'ADP-02', title:'নেটওয়ার্ক সরঞ্জাম, পর্যায় ১ / Network equipment, phase 1', status:'ACTIVE', plan:'2_CATEGORY', fy:'2026-2027', funding:G,
    geo:'Chattogram Division / চট্টগ্রাম বিভাগ', offices:new Array(11).fill('x').map(function(_,i){return 'District hospital '+(i+1);}),
    products:prods([['Network routers / নেটওয়ার্ক রাউটার','3257105',64,12],['Network switches / নেটওয়ার্ক সুইচ','3257105',128,30]]),
    docs:[], created:'2026-08-02T09:00:00', activated:'2026-08-05T16:00:00', archived:null, first:'2026-09-02T12:00:00', last:'2026-10-09T13:00:00', grns:6},
   {id:203, code:P+'ADP-03', title:'প্রিন্টার ও স্ক্যানার / Printers and scanners', status:'ACTIVE', plan:'1_BLANKET', fy:'2026-2027', funding:G,
    geo:'Khulna Division / খুলনা বিভাগ', offices:new Array(10).fill('x').map(function(_,i){return 'District hospital '+(i+1);}),
    products:[], docs:[{name:'DGHS-ORD-2026-131.pdf', at:'2026-08-11T09:40:00', by:'Mahmuda Khatun'}], created:'2026-08-10T09:00:00', activated:'2026-08-12T10:00:00', archived:null, first:'2026-08-29T12:00:00', last:'2026-10-18T11:00:00', grns:9},
   {id:204, code:P+'ADP-04', title:'জেলা হাসপাতালে ডেস্কটপ কম্পিউটার, পর্যায় ২ / Desktop computers for district hospitals, phase 2', status:'DRAFT', plan:'2_CATEGORY', fy:'2026-2027', funding:G,
    geo:'Rajshahi Division / রাজশাহী বিভাগ', offices:new Array(16).fill('x').map(function(_,i){return 'District hospital '+(i+1);}),
    products:prods([['Desktop computers / ডেস্কটপ কম্পিউটার','3257105',280,0],['UPS / ইউপিএস','3257105',280,0]]),
    docs:[{name:'DGHS-ORD-2026-162.pdf', at:'2026-10-14T14:05:00', by:'Tanvir Hossain'}], created:'2026-10-12T09:00:00', activated:null, archived:null, first:null, last:null, grns:0},
   {id:205, code:P+'ADP-05', title:'টেলিমেডিসিন কিট / Telemedicine kits', status:'DRAFT', plan:'2_CATEGORY', fy:'2026-2027', funding:G,
    geo:null, offices:[], products:[], docs:[], created:'2026-10-19T09:00:00', activated:null, archived:null, first:null, last:null, grns:0},
   {id:206, code:P+'REV-01', title:'পাইলট ল্যাপটপ / Pilot laptops', status:'ARCHIVED', plan:'2_CATEGORY', fy:'2025-2026', funding:'Revenue / রাজস্ব',
    geo:'Dhaka Division / ঢাকা বিভাগ', offices:['DGHS headquarters','Dhaka Medical College Hospital','Mugda Medical College Hospital'],
    products:prods([['Laptops / ল্যাপটপ','3257105',40,40]]),
    docs:[{name:'DGHS-ORD-2025-071.pdf', at:'2025-11-02T09:00:00', by:'Tanvir Hossain'}], created:'2025-10-28T09:00:00', activated:'2025-11-03T10:00:00', archived:'2026-07-01T10:00:00', first:'2025-12-01T12:00:00', last:'2026-05-20T12:00:00', grns:5}
  ];
  var activity = [
   {type:'created', at:'2025-10-27T09:00:00', by:'Dr. Nasima Akhter', target:null},
   {type:'archived', at:'2026-07-01T10:00:00', by:'Dr. Nasima Akhter', target:P+'REV-01'},
   {type:'activated', at:'2026-07-21T11:30:00', by:'Dr. Nasima Akhter', target:P+'ADP-01'},
   {type:'activated', at:'2026-08-05T16:00:00', by:'Dr. Nasima Akhter', target:P+'ADP-02'},
   {type:'activated', at:'2026-08-12T10:00:00', by:'Dr. Nasima Akhter', target:P+'ADP-03'},
   {type:'receipt', at:'2026-10-09T13:00:00', by:'Shirin Sultana', target:P+'ADP-02', grn:'GRN-2026-004417', units:14, lines:2},
   {type:'codeCreated', at:'2026-10-12T09:00:00', by:'Tanvir Hossain', target:P+'ADP-04'},
   {type:'doc', at:'2026-10-14T14:05:00', by:'Tanvir Hossain', target:P+'ADP-04'},
   {type:'receipt', at:'2026-10-15T15:20:00', by:'Abdul Karim', target:P+'ADP-01', grn:'GRN-2026-004560', units:36, lines:3},
   {type:'receipt', at:'2026-10-18T11:00:00', by:'Rokeya Begum', target:P+'ADP-03', grn:'GRN-2026-004602', units:9, lines:4},
   {type:'codeCreated', at:'2026-10-19T09:00:00', by:'Mahmuda Khatun', target:P+'ADP-05'}
  ];
  return {
    id:31, live:false, title:'জেলা হাসপাতাল ডিজিটাল সেবা সম্প্রসারণ / District hospital digital services', purpose:'৬৪টি জেলা হাসপাতালের জন্য কম্পিউটার, প্রিন্টার ও নেটওয়ার্ক সরঞ্জাম',
    status:'Active', funding:'ADP', owner:'স্বাস্থ্য অধিদপ্তর / Directorate General of Health Services', created:'2025-10-27T09:00:00',
    rules:{docs:true, overshoot:false, metadata:true},
    team:[{d:'HEAD',n:'Dr. Nasima Akhter',j:'Deputy Director, Planning'},{d:'OFFICER',n:'Tanvir Hossain',j:'Assistant Director'},{d:'OFFICER',n:'Mahmuda Khatun',j:'Programme Officer'},{d:'MONITOR',n:'Kamrul Islam',j:'Audit Officer'}],
    delivered:57.1, receipts:43, codes:codes, activity:activity
  };
 }
};

/* ------------------------------------------------------------------ state */
var state = {opt:'a', role:'HEAD', data:'live', lang:'en', tab:'all', q:'', open:{}, act:'all', actAll:false, drawer:null};
try{
  var saved = JSON.parse(localStorage.getItem('mg-state')||'{}');
  ['opt','role','data'].forEach(function(k){ if(saved[k]) state[k]=saved[k]; });
  state.lang = localStorage.getItem('mg-lang') || 'en';
}catch(e){}
var P = DATASETS[state.data]();
function save(){ try{ localStorage.setItem('mg-state', JSON.stringify({opt:state.opt, role:state.role, data:state.data})); localStorage.setItem('mg-lang', state.lang); }catch(e){} }

/* ------------------------------------------------------------------ helpers */
function t(k, v){ var s = (S[state.lang][k] !== undefined ? S[state.lang][k] : S.en[k]); if(s===undefined) return k; return s.replace(/\{(\w+)\}/g, function(_,n){ return v && v[n]!==undefined ? v[n] : ''; }); }
function esc(s){ return String(s==null?'':s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
function loc(){ return state.lang==='bn' ? 'bn-BD' : 'en-IN'; }
function n(x){ return Number(x).toLocaleString(loc()); }
function pct(x){ return Number(x).toLocaleString(loc(), {maximumFractionDigits:1, minimumFractionDigits:1})+'%'; }
function d(iso){ if(!iso) return ''; return new Intl.DateTimeFormat(state.lang==='bn'?'bn-BD':'en-GB', {day:'numeric', month:'short', year:'numeric'}).format(new Date(iso)); }
function dt(iso){ return new Intl.DateTimeFormat(state.lang==='bn'?'bn-BD':'en-GB', {day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}).format(new Date(iso)); }
function icon(name){ return '<i class="fas '+name+'" aria-hidden="true"></i>'; }
function head(){ return P.team.filter(function(m){ return m.d==='HEAD'; })[0]; }
function me(){ var m = P.team.filter(function(x){ return x.d===state.role; })[0]; return m ? m.n : t('role_'+state.role); }
function codeById(id){ return P.codes.filter(function(c){ return c.id===id; })[0]; }

var CAN = {
  HEAD:{create:1, edit:1, del:1, activate:1, archive:1, docs:1, team:1, settings:1, retro:1},
  OFFICER:{create:1, edit:1, del:1, docs:1, retro:1},
  SUPPORT:{retro:1},
  MONITOR:{}
};
function can(a){ return !!CAN[state.role][a]; }
function writer(){ return can('edit'); }

function prefix(){
  var first = P.codes[0] && P.codes[0].code, m = first && first.match(/^(.+-)[^-]+-[^-]+$/);
  if(!m) return '';
  return P.codes.every(function(c){ return c.code.indexOf(m[1])===0; }) ? m[1] : '';
}
function short(c){ var p = prefix(); return p ? c.code.slice(p.length) : c.code; }
function segs(c){
  var p = prefix();
  if(!p) return '<span class="gs-segs gs-segs--sm" role="group" aria-label="'+esc(c.code)+'"><span class="gs-seg" data-tone="primary"><span class="gs-seg__v">'+esc(c.code)+'</span></span></span>';
  return '<span class="gs-segs gs-segs--sm" role="group" aria-label="'+esc(c.code)+'" title="'+esc(c.code)+'"><span class="gs-seg" data-tone="muted"><span class="gs-seg__v">'+esc(p)+'</span></span><span class="gs-seg" data-tone="primary"><span class="gs-seg__v">'+esc(c.code.slice(p.length))+'</span></span></span>';
}
var STATUS = {DRAFT:['doc-draft','fa-pen'], ACTIVE:['success','fa-circle-play'], ARCHIVED:['status-archived','fa-box-archive']};
function badge(status){ var s = STATUS[status]; return '<span class="gs-badge" data-tone="'+s[0]+'" data-status="'+status.toLowerCase()+'">'+icon(s[1]+' gs-badge__icon')+'<span class="gs-badge__label">'+esc(t('s_'+status))+'</span></span>'; }
function tag(text, tone){ return '<span class="gs-tag gs-tag--'+(tone||'secondary')+' gs-tag--pill">'+esc(text)+'</span>'; }
function btn(label, opts){
  opts = opts || {};
  var cls = 'gs-btn gs-btn--'+(opts.tone||'primary')+(opts.outline?' gs-btn--outline':'')+(opts.size?' gs-btn--'+opts.size:' gs-btn--sm');
  var attrs = opts.attrs || '';
  if(opts.disabled) return '<button type="button" class="'+cls+'" disabled title="'+esc(opts.why||'')+'" '+attrs+'>'+(opts.icon?icon(opts.icon):'')+esc(label)+'</button>';
  if(opts.href) return '<a class="'+cls+'" href="'+esc(opts.href)+'" data-link="'+esc(label)+'" '+attrs+'>'+(opts.icon?icon(opts.icon):'')+esc(label)+'</a>';
  return '<button type="button" class="'+cls+'" '+attrs+'>'+(opts.icon?icon(opts.icon):'')+esc(label)+'</button>';
}
function url(path){ return BASE+'/'+P.id+(path?'/'+path:''); }

/* ------------------------------------------------------------------ derived */
function checks(c){
  var list = [];
  list.push({key:'products', ok:c.plan==='1_BLANKET' || c.products.length>0});
  list.push({key:'scope', ok:!!c.geo && c.offices.length>0});
  if(P.rules.docs) list.push({key:'order', ok:c.docs.length>0});
  return list;
}
function missing(c){ return checks(c).filter(function(x){ return !x.ok; }); }
function ready(c){ return missing(c).length===0; }
function needsSetup(c){ return c.status!=='ARCHIVED' && missing(c).length>0; }
function planned(c){ return c.products.reduce(function(s,p){ return s+p.planned; },0); }
function received(c){ return c.products.reduce(function(s,p){ return s+p.received; },0); }
function officesLabel(c){ return c.offices.length===1 ? t('oneOffice') : t('nOffices',{n:n(c.offices.length)}); }

function readiness(){
  var heads = P.team.filter(function(m){ return m.d==='HEAD'; }).length, officers = P.team.filter(function(m){ return m.d==='OFFICER'; }).length;
  var active = P.codes.filter(function(c){ return c.status==='ACTIVE'; });
  var items = [
    {label:t('r_head'), done:heads===1, action:can('team')?{label:t('assign'), go:'team'}:null},
    {label:t('r_officer'), done:officers>=1, action:can('team')?{label:t('assign'), go:'team'}:null},
    {label:t('r_active'), done:active.length>0, action:null}
  ];
  if(P.rules.docs){
    var withDoc = active.filter(function(c){ return c.docs.length>0; }).length;
    items.push({label:t('r_orders'), value:n(withDoc)+' / '+n(active.length), done:active.length>0 && withDoc===active.length, action:null});
  }
  return items;
}
function todos(){
  var list = [];
  var heads = P.team.filter(function(m){ return m.d==='HEAD'; }).length, officers = P.team.filter(function(m){ return m.d==='OFFICER'; }).length;
  if(!heads) list.push({tone:'danger', icon:'fa-user-plus', title:t('todoTeam',{r:t('role_HEAD').toLowerCase()}), why:t('todoTeamWhy'), who:t('whoHead'), act:can('team')?{label:t('assign'), go:'team'}:null});
  if(!officers) list.push({tone:'danger', icon:'fa-user-plus', title:t('todoTeam',{r:t('role_OFFICER').toLowerCase()}), why:t('todoTeamWhy'), who:t('whoHead'), act:can('team')?{label:t('assign'), go:'team'}:null});
  var noOrder = P.codes.filter(function(c){ return c.status==='ACTIVE' && P.rules.docs && !c.docs.length; });
  if(noOrder.length>2) list.push({tone:'warning', icon:'fa-file-pdf', title:t('todoOrderMany',{n:n(noOrder.length)}), why:t('todoOrderManyWhy'), who:t('whoEditors'), act:writer()?{label:t('showCodes'), go:'setup'}:null});
  else noOrder.forEach(function(c){ list.push({tone:'warning', icon:'fa-file-pdf', title:t('todoOrder',{c:short(c)}), why:t('todoOrderWhy'), who:t('whoEditors'), act:can('docs')?{label:t('attach'), cmd:'attach', id:c.id}:null}); });
  P.codes.filter(function(c){ return c.status==='DRAFT'; }).forEach(function(c){
    var m = missing(c);
    if(!m.length) list.push({tone:'info', icon:'fa-circle-play', title:t('todoReady',{c:short(c)}), why:t('todoReadyWhy'), who:t('whoHead'), act:can('activate')?{label:t('activate'), cmd:'activate', id:c.id, tone:'success'}:null});
    else list.push({tone:'info', icon:'fa-pen', title:t('todoFinish',{c:short(c)}), why:t('todoFinishWhy',{m:m.map(function(x){ return t('m_'+x.key); }).join(', ')}), who:t('whoEditors'), act:can('edit')?{label:t('edit'), cmd:'edit', id:c.id}:null});
  });
  return list;
}

/* ------------------------------------------------------------------ pieces */
function headerHtml(){
  var fys = P.codes.map(function(c){ return c.fy; }).filter(function(v,i,a){ return a.indexOf(v)===i; }).join(' · ');
  var actions = '';
  if(can('create')) actions += btn(t('newCode'), {icon:'fa-plus', href:url('tracking-codes/create'), size:'md'});
  if(can('settings')) actions += btn(t('settings'), {icon:'fa-gear', tone:'secondary', outline:true, href:url('edit'), size:'md'});
  return '<nav class="mg-crumb" aria-label="Breadcrumb"><a href="#">'+esc(t('crumbHome'))+'</a> / <a href="'+esc(BASE)+'" data-link="'+esc(t('crumbProgrammes'))+'">'+esc(t('crumbProgrammes'))+'</a> / '+esc(t('manageTitle'))+'</nav>'+
  '<header class="mg-head"><div class="mg-head__text"><h1 class="mg-title">'+esc(P.title)+'</h1><p class="mg-sub">'+esc(P.purpose)+'</p>'+
  '<div class="mg-meta">'+'<span class="gs-badge" data-tone="success" data-status="active">'+icon('fa-circle-check gs-badge__icon')+'<span class="gs-badge__label">'+esc(t('st_'+P.status))+'</span></span>'+
  '<span>'+esc(P.funding)+' · '+esc(P.owner)+'</span><span class="mg-meta__sep">'+esc(fys)+'</span><span class="mg-meta__sep">'+esc(t('codesN',{n:n(P.codes.length)}))+'</span></div></div>'+
  (actions?'<div class="mg-head__actions">'+actions+'</div>':'')+'</header>';
}
function tabsHtml(){
  var last = P.codes.map(function(c){ return c.last; }).filter(Boolean).sort().pop();
  var tp = P.codes.filter(function(c){ return c.status!=='DRAFT'; }).reduce(function(s,c){ return s+planned(c); },0);
  var tr = P.codes.reduce(function(s,c){ return s+received(c); },0);
  var snap = last ? '<span>'+esc(t('snapDelivered',{p:pct(P.delivered)}))+'</span>'+(tp?'<span><strong>'+esc(t('snapUnits',{r:n(tr), t:n(tp)}))+'</strong></span>':'')+'<span>'+esc(t('snapReceipts',{n:n(P.receipts)}))+' · '+esc(t('snapLast',{d:d(last)}))+'</span>' : '<span>'+esc(t('snapNone'))+'</span>';
  return '<div class="mg-tabsrow"><nav class="gs-level-switch" aria-label="Programme">'+
    '<a class="gs-level-switch__option" href="'+esc(url(''))+'" data-link="'+esc(t('tabMonitor'))+'">'+esc(t('tabMonitor'))+'</a>'+
    '<a class="gs-level-switch__option" href="#" aria-current="page">'+esc(t('tabManage'))+'</a>'+
    '<a class="gs-level-switch__option" href="'+esc(url('report'))+'" data-link="'+esc(t('tabReport'))+'">'+esc(t('tabReport'))+'</a>'+
    '<a class="gs-level-switch__option" href="#" data-link="'+esc(t('tabOffices'))+'">'+esc(t('tabOffices'))+'</a></nav>'+
    '<div class="mg-snap">'+snap+'<a href="'+esc(url(''))+'" data-link="'+esc(t('openMonitor'))+'">'+esc(t('openMonitor'))+' '+icon('fa-arrow-right')+'</a></div></div>';
}
function bandHtml(){
  var list = todos(), r = readiness();
  // Team and order-PDF gaps already appear as to-dos; only "no active code" is readiness-only.
  var gaps = list.length + (P.codes.some(function(c){ return c.status==='ACTIVE'; }) ? 0 : 1);
  var h = head();
  var verdict = gaps===0 ? t('verdictOk') : (gaps===1 ? t('verdict1') : t('verdictN',{n:n(gaps)}));
  var next = !h ? t('nextNoHead') : (state.role==='HEAD' ? t('nextHead') : (state.role==='OFFICER' ? t('nextOfficer',{p:h.n}) : t('nextRead',{p:h.n})));
  var tone = gaps ? 'warning' : 'success';
  var callout = '<div class="gs-status-callout" data-status="'+tone+'"><span class="gs-badge" data-tone="'+tone+'" data-status="'+tone+'">'+icon((gaps?'fa-triangle-exclamation':'fa-circle-check')+' gs-badge__icon')+'<span class="gs-badge__label">'+esc(gaps?t('clWarn'):t('clOk'))+'</span></span><div><strong>'+esc(verdict)+'</strong><p>'+esc(next)+'</p></div></div>';
  var todo = list.length ? '<ul class="mg-todo">'+list.map(function(x){
      var act = x.act ? (x.act.go ? btn(x.act.label,{tone:'secondary', outline:true, attrs:'data-go="'+x.act.go+'"'}) : btn(x.act.label,{tone:x.act.tone||'primary', attrs:'data-cmd="'+x.act.cmd+'" data-id="'+x.act.id+'"'})) : '<span class="mg-todo__who">'+icon('fa-lock')+' '+esc(x.who)+'</span>';
      return '<li class="mg-todo__item" data-tone="'+x.tone+'">'+icon(x.icon)+'<div><strong>'+esc(x.title)+'</strong><small>'+esc(x.why)+'</small></div>'+act+'</li>';
    }).join('')+'</ul>' : '<p class="mg-todo__empty">'+esc(t('todoNone'))+'</p>';
  var done = r.filter(function(x){ return x.done; }).length, total = r.length, complete = done===total;
  var readyHtml = '<section class="gs-readiness'+(complete?' gs-readiness--complete':'')+'"><div class="gs-readiness__head"><div class="gs-readiness__text"><strong class="gs-readiness__title">'+esc(t('readyTitle'))+'</strong><p class="gs-readiness__caption">'+esc(t('readyCaption'))+'</p></div><span class="gs-readiness__count">'+esc(t('readyCount',{d:n(done), t:n(total)}))+'</span></div>'+
    '<div class="gs-progress gs-progress--md gs-tone-'+(complete?'success':'primary')+'"><div class="gs-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="'+total+'" aria-valuenow="'+done+'" aria-label="'+esc(t('readyTitle'))+'"><span class="gs-progress__bar" style="width:'+Math.round(done/Math.max(1,total)*100)+'%"></span></div></div>'+
    '<ul class="gs-readiness__list">'+r.map(function(x){
      return '<li class="gs-readiness__item'+(x.done?' gs-readiness__item--done':'')+'">'+icon((x.done?'fa-circle-check':'fa-circle')+' gs-readiness__icon')+'<span class="gs-readiness__label">'+esc(x.label)+(x.value?': <strong>'+esc(x.value)+'</strong>':'')+'</span>'+
        (!x.done && x.action ? btn(x.action.label,{tone:'primary', attrs:'data-go="'+x.action.go+'"'}) : (!x.done ? '<span class="gs-readiness__todo" aria-hidden="true">'+esc(state.lang==='bn'?'বাকি':'To do')+'</span>' : ''))+'</li>';
    }).join('')+'</ul></section>';
  return '<div class="mg-band"><div class="mg-panel"><div class="mg-panel__body">'+callout+todo+'</div></div>'+readyHtml+'</div>';
}

function checkChips(c){
  if(c.status==='ARCHIVED') return '<span class="mg-subtle">—</span>';
  return '<div class="mg-checks">'+checks(c).map(function(x){ return '<span class="mg-check" data-ok="'+x.ok+'">'+icon(x.ok?'fa-check':'fa-circle-exclamation')+esc(t('c_'+x.key))+'<span class="mg-sr"> '+(x.ok?'ok':'missing')+'</span></span>'; }).join('')+'</div>';
}
function statusNote(c){
  if(c.status==='ACTIVE') return '<span class="mg-cellnote">'+esc(t('since',{d:d(c.activated)}))+'</span>'+(c.plan!=='1_BLANKET'?'<span class="mg-cellnote mg-num">'+esc(t('receivedOf',{r:n(received(c)), t:n(planned(c))}))+'</span>':'');
  if(c.status==='DRAFT') return '<span class="mg-cellnote">'+esc(t('createdOn',{d:d(c.created)}))+'</span>';
  return '<span class="mg-cellnote">'+esc(t('archivedOn',{d:d(c.archived)}))+'</span>';
}
function primaryAction(c){
  var label = short(c);
  if(c.status==='DRAFT'){
    if(can('activate') && ready(c)) return btn(t('activate'),{tone:'success', icon:'fa-circle-play', attrs:'data-cmd="activate" data-id="'+c.id+'"'});
    if(can('edit')) return btn(t('edit'),{tone:'primary', outline:true, icon:'fa-pen', href:url('tracking-codes/'+c.id+'/edit')});
  }
  if(c.status==='ACTIVE' && P.rules.docs && !c.docs.length && can('docs')) return btn(t('attach'),{tone:'warning', icon:'fa-file-arrow-up', attrs:'data-cmd="attach" data-id="'+c.id+'"'});
  return btn(t('open'),{tone:'secondary', outline:true, href:url('tracking-codes/'+c.id), attrs:'aria-label="'+esc(t('a_open'))+' '+esc(label)+'"'});
}
function menuItem(label, opts){
  if(opts.why) return '<button type="button" disabled'+(opts.danger?' data-danger':'')+'>'+icon(opts.icon)+'<span>'+esc(label)+'<small>'+icon('fa-lock')+' '+esc(opts.why)+'</small></span></button>';
  if(opts.href) return '<a href="'+esc(opts.href)+'" data-link="'+esc(label)+'">'+icon(opts.icon)+'<span>'+esc(label)+'</span></a>';
  return '<button type="button" data-cmd="'+opts.cmd+'" data-id="'+opts.id+'"'+(opts.danger?' data-danger':'')+'>'+icon(opts.icon)+'<span>'+esc(label)+'</span></button>';
}
function moreMenu(c){
  var items = [], w = writer();
  var m = missing(c).map(function(x){ return t('m_'+x.key); }).join(', ');
  items.push(menuItem(t('a_open'),{icon:'fa-up-right-from-square', href:url('tracking-codes/'+c.id)}));
  if(c.status!=='DRAFT') items.push(menuItem(t('a_monitor'),{icon:'fa-chart-line', href:url('')+'#code-'+c.id}));
  if(w && c.status==='DRAFT'){
    items.push(menuItem(t('a_edit'),{icon:'fa-pen', href:url('tracking-codes/'+c.id+'/edit')}));
    items.push(menuItem(t('a_activate'),{icon:'fa-circle-play', cmd:'activate', id:c.id, why:!can('activate')?t('lockHead'):(!ready(c)?t('lockChecks',{m:m}):null)}));
  }
  if(w && c.status!=='ARCHIVED') items.push(menuItem(c.docs.length?t('a_replace'):t('a_attach'),{icon:'fa-file-arrow-up', cmd:'attach', id:c.id}));
  if(w && c.status==='DRAFT'){ items.push('<hr>'); items.push(menuItem(t('a_delete'),{icon:'fa-trash', cmd:'delete', id:c.id, danger:true})); }
  if(w && c.status==='ACTIVE'){ items.push('<hr>'); items.push(menuItem(t('a_archive'),{icon:'fa-box-archive', cmd:'archive', id:c.id, danger:true, why:!can('archive')?t('lockHead'):null})); }
  if(!w) items.push('<hr><span class="mg-lock" style="padding:6px 10px">'+icon('fa-eye')+' '+esc(t('readOnly'))+'</span>');
  return '<details class="mg-more"><summary class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" aria-label="'+esc(t('moreFor',{c:short(c)}))+'">'+icon('fa-ellipsis')+'</summary><div class="mg-menu">'+items.join('')+'</div></details>';
}

function detailHtml(c, inDrawer){
  var prodHtml;
  if(c.plan==='1_BLANKET') prodHtml = '<p class="mg-muted" style="margin:0">'+esc(t('d_blanket'))+'</p>';
  else if(!c.products.length) prodHtml = '<div class="mg-docs__empty">'+esc(t('noProducts'))+(can('edit')&&c.status==='DRAFT'?btn(t('edit'),{icon:'fa-pen', href:url('tracking-codes/'+c.id+'/edit')}):'')+'</div>';
  else prodHtml = '<div class="mg-products__wrap"><table class="mg-products"><thead><tr><th>'+esc(t('d_product'))+'</th><th>'+esc(t('d_econ'))+'</th><th class="mg-r">'+esc(t('d_planned'))+'</th>'+(c.status!=='DRAFT'?'<th class="mg-r">'+esc(t('d_received'))+'</th>':'')+'</tr></thead><tbody>'+
    c.products.map(function(p){ return '<tr><td class="mg-products__name">'+esc(p.name)+'</td><td class="mg-subtle">'+esc(p.econ)+'</td><td class="mg-r">'+n(p.planned)+'</td>'+(c.status!=='DRAFT'?'<td class="mg-r">'+n(p.received)+'</td>':'')+'</tr>'; }).join('')+
    '</tbody><tfoot><tr><td colspan="2">'+esc(t('d_total'))+'</td><td class="mg-r">'+n(planned(c))+'</td>'+(c.status!=='DRAFT'?'<td class="mg-r">'+n(received(c))+'</td>':'')+'</tr></tfoot></table></div>';
  var kv = [[t('d_plan'), t('plan_'+c.plan)], [t('d_geo'), c.geo || t('noScope')], [t('d_offices'), c.offices.length ? (c.offices.length===1 ? c.offices[0] : officesLabel(c)) : t('noScope')], [t('d_fy'), c.fy], [t('d_funding'), c.funding]];
  var kvHtml = '<dl class="gs-kv" style="--gs-kv-columns:1">'+kv.map(function(x){ return '<div class="gs-kv__item"><dt class="gs-kv__label">'+esc(x[0])+'</dt><dd class="gs-kv__value">'+esc(x[1])+'</dd></div>'; }).join('')+'</dl>';
  var steps = [[t('l_created'), c.created], [t('l_activated'), c.activated], [t('l_first'), c.first]];
  if(c.archived || c.status==='ACTIVE') steps.push([t('l_archived'), c.archived]);
  var life = '<ol class="gs-monitor-lifecycle">'+steps.map(function(s){ return '<li>'+icon(s[1]?'fa-circle-check':'fa-circle')+'<strong>'+esc(s[0])+'</strong><span>'+esc(s[1]?d(s[1]):t('l_pending'))+'</span></li>'; }).join('')+'</ol>';
  var docs = c.docs.length ? '<ul class="mg-docs">'+c.docs.map(function(x){ return '<li>'+icon('fa-file-pdf')+'<span>'+esc(x.name)+'</span><small>'+esc(d(x.at))+' · '+esc(x.by)+'</small></li>'; }).join('')+'</ul>'
    : '<div class="mg-docs__empty"><span>'+icon('fa-file-circle-exclamation')+' '+esc(t('d_docsNone'))+' '+esc(P.rules.docs?t('d_docsReq'):t('d_docsOpt'))+'</span>'+(can('docs')&&c.status!=='ARCHIVED'?btn(t('a_attach'),{tone:'warning', icon:'fa-file-arrow-up', attrs:'data-cmd="attach" data-id="'+c.id+'"'}):'')+'</div>';
  var zone = '';
  if(c.status==='DRAFT'){
    var m = missing(c).map(function(x){ return t('m_'+x.key); }).join(', ');
    var aWhy = !can('activate') ? t('lockHead') : (!ready(c) ? t('lockChecks',{m:m}) : null);
    zone += actionRow(t('ar_activate'), t('ar_activateWhy'), aWhy, btn(t('a_activate'),{tone:'success', icon:'fa-circle-play', disabled:!!aWhy, why:aWhy, attrs:'data-cmd="activate" data-id="'+c.id+'"'}), 'act-'+c.id);
    if(writer()) zone += actionRow(t('ar_delete'), t('ar_deleteWhy'), null, btn(t('a_delete'),{tone:'danger', outline:true, icon:'fa-trash', attrs:'data-cmd="delete" data-id="'+c.id+'"'}), 'del-'+c.id);
  } else if(c.status==='ACTIVE'){
    var rWhy = !can('archive') ? t('lockHead') : null;
    zone += actionRow(t('ar_archive'), t('ar_archiveWhy'), rWhy, btn(t('a_archive'),{tone:'danger', outline:true, icon:'fa-box-archive', disabled:!!rWhy, why:rWhy, attrs:'data-cmd="archive" data-id="'+c.id+'"'}), 'arc-'+c.id);
  } else zone = '<p class="mg-muted" style="margin:0">'+icon('fa-lock')+' '+esc(t('ar_archivedRO'))+'</p>';
  return '<div class="mg-dgrid"><div class="mg-dsec"><h4 class="mg-h4">'+esc(t('d_products'))+'</h4>'+prodHtml+'</div>'+
    '<div class="mg-stack"><div class="mg-dsec"><h4 class="mg-h4">'+esc(t('d_scope'))+'</h4>'+kvHtml+'</div>'+
    '<div class="mg-dsec"><h4 class="mg-h4">'+esc(t('d_docs'))+'</h4>'+docs+'</div></div>'+
    '<div class="mg-dsec" style="grid-column:1/-1"><h4 class="mg-h4">'+esc(t('d_life'))+'</h4>'+life+'</div>'+
    (writer() || c.status==='ARCHIVED' ? '<div class="mg-dsec mg-danger" style="grid-column:1/-1"><h4 class="mg-h4">'+esc(t('d_change'))+'</h4>'+zone+'</div>' : '')+'</div>';
}
function actionRow(title, desc, why, control, id){
  return '<div class="gs-action-row'+(why?' gs-action-row--unavailable':'')+'" id="mg-ar-'+id+'"><div class="gs-action-row__text"><strong class="gs-action-row__title">'+esc(title)+'</strong><p class="gs-action-row__description">'+esc(desc)+'</p>'+
    (why?'<p class="gs-action-row__why">'+icon('fa-lock')+' '+esc(why)+'</p>':'')+'</div><div class="gs-action-row__actions">'+control+'</div></div>';
}

function filtered(){
  var q = state.q.trim().toLowerCase();
  return P.codes.filter(function(c){
    if(state.tab==='setup' && !needsSetup(c)) return false;
    if(['DRAFT','ACTIVE','ARCHIVED'].indexOf(state.tab)>=0 && c.status!==state.tab) return false;
    if(q && (c.code+' '+c.title+' '+(c.geo||'')+' '+c.offices.join(' ')).toLowerCase().indexOf(q)<0) return false;
    return true;
  }).sort(function(a,b){
    var o = {DRAFT:0, ACTIVE:1, ARCHIVED:2};
    return (o[a.status]-o[b.status]) || (needsSetup(b)-needsSetup(a)) || a.code.localeCompare(b.code, undefined, {numeric:true});
  });
}
function filterBar(){
  var counts = {all:P.codes.length, setup:P.codes.filter(needsSetup).length};
  ['DRAFT','ACTIVE','ARCHIVED'].forEach(function(s){ counts[s] = P.codes.filter(function(c){ return c.status===s; }).length; });
  return '<div class="mg-filter"><div class="mg-chips" role="group" aria-label="'+esc(t('th_status'))+'">'+['all','setup','DRAFT','ACTIVE','ARCHIVED'].map(function(k){
      return '<button type="button" data-tab="'+k+'" aria-pressed="'+(state.tab===k)+'">'+esc(t('f_'+k))+'<b>'+n(counts[k])+'</b></button>';
    }).join('')+'</div><label class="mg-search"><span class="mg-sr">'+esc(t('search'))+'</span>'+icon('fa-magnifying-glass')+'<input type="search" id="mg-q" value="'+esc(state.q)+'" placeholder="'+esc(t('search'))+'"></label></div>';
}
function emptyCodes(){
  if(!P.codes.length) return '<div class="gs-empty" style="padding:32px 16px">'+icon('fa-barcode gs-empty__icon')+'<h3 class="gs-empty__title">'+esc(t('noCodes'))+'</h3><div>'+esc(t('noCodesHint'))+'</div>'+(can('create')?'<div class="gs-empty__actions">'+btn(t('newCode'),{icon:'fa-plus', href:url('tracking-codes/create')})+'</div>':'')+'</div>';
  return '<div class="gs-empty" style="padding:28px 16px">'+icon('fa-filter gs-empty__icon')+'<h3 class="gs-empty__title">'+esc(t('emptyFilter'))+'</h3><div class="gs-empty__actions">'+btn(t('clearFilter'),{tone:'secondary', outline:true, attrs:'data-tab="all"'})+'</div></div>';
}
function cardHtml(c, lane){
  var meta = [t('plan_'+c.plan)];
  if(c.plan!=='1_BLANKET' && c.products.length) meta.push(t('products',{n:n(c.products.length)}));
  meta.push(c.geo || t('noScope'));
  var foot = c.status==='ACTIVE' && c.plan!=='1_BLANKET' ? '<span class="mg-cellnote mg-num" style="margin:0">'+esc(t('receivedOf',{r:n(received(c)), t:n(planned(c))}))+'</span>' : statusNote(c);
  return '<article class="mg-card" data-code="'+c.id+'"><div class="mg-card__top">'+segs(c)+badge(c.status)+'</div>'+
    '<h3 class="mg-card__title"><button type="button" data-drawer="'+c.id+'">'+esc(c.title)+'</button></h3>'+
    '<div class="mg-card__meta">'+meta.map(function(x){ return '<span>'+esc(x)+'</span>'; }).join('')+'</div>'+
    checkChips(c)+
    '<div class="mg-card__foot"><div>'+foot+'</div><div class="mg-actions">'+primaryAction(c)+btn(t('details'),{tone:'secondary', outline:true, attrs:'data-drawer="'+c.id+'"'})+'</div></div></article>';
}

function codesPanelA(){
  var list = filtered();
  var rows = list.map(function(c){
    var open = !!state.open[c.id];
    return '<tr class="mg-row'+(c.status==='ARCHIVED'?' mg-row--archived':'')+'" data-open="'+open+'" id="code-'+c.id+'">'+
      '<td><button type="button" class="mg-expand" data-toggle="'+c.id+'" aria-expanded="'+open+'" aria-controls="mg-detail-'+c.id+'" aria-label="'+esc(open?t('collapse',{c:short(c)}):t('expand',{c:short(c)}))+'">'+icon(open?'fa-chevron-down':'fa-chevron-right')+'</button></td>'+
      '<td><div class="mg-id">'+segs(c)+'<span class="mg-id__title">'+esc(c.title)+'</span><span class="mg-id__sub">'+(c.plan==='1_BLANKET'?esc(t('plan_1_BLANKET')):(c.products.length?esc(t('products',{n:n(c.products.length)}))+' · '+esc(t('unitsPlanned',{n:n(planned(c))})):esc(t('noProducts'))))+'</span></div></td>'+
      '<td class="mg-plan">'+esc(t('plan_'+c.plan))+'<span class="mg-cellnote">'+esc(c.geo||t('noScope'))+(c.offices.length?' · '+esc(officesLabel(c)):'')+'</span><span class="mg-cellnote mg-num">'+esc(t('d_fy'))+' '+esc(c.fy)+'</span></td>'+
      '<td>'+checkChips(c)+'</td>'+
      '<td class="mg-status">'+badge(c.status)+statusNote(c)+'</td>'+
      '<td><div class="mg-actions">'+primaryAction(c)+moreMenu(c)+'</div></td></tr>'+
      (open?'<tr class="mg-detail" id="mg-detail-'+c.id+'"><td colspan="6">'+detailHtml(c)+'</td></tr>':'');
  }).join('');
  var table = list.length ? '<div class="gs-table-wrap mg-table"><table class="gs-table mg-codes"><thead><tr><th scope="col"><span class="mg-sr">'+esc(t('details'))+'</span></th><th scope="col">'+esc(t('th_code'))+'</th><th scope="col">'+esc(t('th_plan'))+'</th><th scope="col">'+esc(t('th_checks'))+'</th><th scope="col">'+esc(t('th_status'))+'</th><th scope="col" style="text-align:right">'+esc(t('th_actions'))+'</th></tr></thead><tbody>'+rows+'</tbody></table></div>'+
    '<div class="mg-cards">'+list.map(function(c){ return cardHtml(c); }).join('')+'</div>' : emptyCodes();
  return '<section class="mg-panel" aria-labelledby="mg-codes-h"><div class="mg-panel__head"><h2 class="mg-panel__title" id="mg-codes-h">'+esc(t('codesTitle'))+'</h2><span class="mg-panel__hint">'+esc(t('codesHint'))+'</span>'+
    (can('create')?'<div class="mg-panel__tools">'+btn(t('newCode'),{icon:'fa-plus', tone:'primary', outline:true, href:url('tracking-codes/create')})+'</div>':'')+'</div>'+filterBar()+table+'</section>';
}

function teamHtml(cols){
  var slots = [['HEAD',true],['OFFICER',true],['SUPPORT',false],['MONITOR',false]];
  var html = '';
  slots.forEach(function(s){
    var people = P.team.filter(function(m){ return m.d===s[0]; });
    if(!people.length) people = [null];
    people.forEach(function(p){
      html += '<div class="gs-role'+(p?'':' gs-role--empty')+(!p&&s[1]?' gs-role--required':'')+'"><div class="gs-role__head"><strong class="gs-role__name">'+esc(t('role_'+s[0]))+'</strong>'+
        (!p&&s[1]?'<span class="gs-role__flag">'+esc(t('needed'))+'</span>':(!s[1]&&!p?'<span class="gs-role__flag gs-role__flag--muted">'+esc(t('optional'))+'</span>':''))+'</div>'+
        (!p?'<p class="gs-role__description">'+esc(t('roleDesc_'+s[0]))+'</p>':'')+
        '<div class="gs-role__body">'+(p?'<span class="gs-avatar gs-avatar--md gs-tone-primary" aria-hidden="true">'+esc(p.n.replace(/^(Dr|Mst|Md)\.\s*/,'').split(/\s+/).slice(0,2).map(function(w){ return w.charAt(0); }).join(''))+'</span><div class="gs-role__person"><span class="gs-role__person-name">'+esc(p.n)+'</span><span class="gs-role__person-meta">'+esc(p.j)+'</span></div>':'')+
        (can('team')?'<div class="gs-role__actions">'+btn(p?t('change'):t('assign'),{tone:p?'light':'primary', outline:!!p, href:url('operation-unit')})+'</div>':'')+'</div></div>';
    });
  });
  return '<section class="mg-panel" id="mg-team" tabindex="-1"><div class="mg-panel__head"><h2 class="mg-panel__title">'+esc(t('teamTitle'))+'</h2>'+(can('team')?'<div class="mg-panel__tools"><a class="mg-link" href="'+esc(url('operation-unit'))+'" data-link="'+esc(t('teamManage'))+'">'+esc(t('teamManage'))+'</a></div>':'')+'</div><div class="mg-panel__body"><div class="gs-roles">'+html+'</div></div></section>';
}
function rulesHtml(){
  var items = [[t('rule_docs'), P.rules.docs?t('yesRequired'):t('notRequired')], [t('rule_over'), P.rules.overshoot?t('allowedWarn'):t('blocked')], [t('rule_meta'), P.rules.metadata?t('yesRequired'):t('notRequired')]];
  return '<section class="mg-panel"><div class="mg-panel__head"><h2 class="mg-panel__title">'+esc(t('rulesTitle'))+'</h2>'+(can('settings')?'<div class="mg-panel__tools">'+btn(t('editRules'),{tone:'secondary', outline:true, href:url('edit')})+'</div>':'')+'</div><div class="mg-panel__body">'+
    '<dl class="gs-kv" style="--gs-kv-columns:1">'+items.map(function(x){ return '<div class="gs-kv__item"><dt class="gs-kv__label">'+esc(x[0])+'</dt><dd class="gs-kv__value"><strong>'+esc(x[1])+'</strong></dd></div>'; }).join('')+'</dl><p class="mg-rule-why">'+esc(t('rulesWhy'))+'</p></div></section>';
}
function toolsHtml(){
  var rows = [];
  if(can('retro')) rows.push(actionRow(t('t_retro'), t('t_retroWhy'), null, btn(t('open'),{tone:'secondary', outline:true, href:url('retrospective')}), 'retro'));
  rows.push(actionRow(t('t_report'), t('t_reportWhy'), null, btn(t('open'),{tone:'secondary', outline:true, href:url('report')}), 'report'));
  if(can('settings')) rows.push(actionRow(t('t_props'), t('t_propsWhy'), null, btn(t('open'),{tone:'secondary', outline:true, href:url('edit')}), 'props'));
  return '<section class="mg-panel"><div class="mg-panel__head"><h2 class="mg-panel__title">'+esc(t('toolsTitle'))+'</h2></div><div class="mg-panel__body mg-tools">'+rows.join('')+'</div></section>';
}
function activityHtml(){
  var setupTypes = ['created','activated','codeCreated','doc','archived','deleted'];
  var all = P.activity.slice().sort(function(a,b){ return a.at<b.at?1:-1; });
  var list = all.filter(function(e){ return state.act==='all' || (state.act==='receipts' ? e.type==='receipt' : setupTypes.indexOf(e.type)>=0); });
  var shown = state.actAll ? list : list.slice(0,6);
  var items = shown.map(function(e){
    var by = e.by || t('unknownActor'), action = '', target = '';
    var codeLabel = e.target ? (prefix() && e.target.indexOf(prefix())===0 ? e.target.slice(prefix().length) : e.target) : '';
    if(e.type==='receipt'){ action = t('ev_receipt',{u:n(e.units)}); target = codeLabel+' · '+e.grn; }
    else if(e.type==='activated'){ by = codeLabel; action = t('ev_activated'); target = e.by ? '· '+e.by : ''; }
    else if(e.type==='created'){ action = t('ev_created'); }
    else { action = t('ev_'+e.type); target = codeLabel; }
    return '<li class="gs-timeline__item"><div><strong>'+esc(by)+'</strong> '+esc(action)+' <span>'+esc(target)+'</span>'+(e.derived?'<span class="mg-act__tag">'+icon('fa-database')+esc(t('ev_fromRecord'))+'</span>':'')+
      (e.type==='receipt'&&e.lines>1?'<span class="mg-act__lines">'+esc(t('ev_lines',{n:n(e.lines)}))+'</span>':'')+'</div><time class="gs-timeline__meta" datetime="'+esc(e.at)+'">'+esc(dt(e.at))+'</time></li>';
  }).join('');
  return '<section class="mg-panel mg-activity"><div class="mg-panel__head"><h2 class="mg-panel__title">'+esc(t('activityTitle'))+'</h2><div class="mg-panel__tools"><div class="mg-chips" role="group" aria-label="'+esc(t('activityTitle'))+'">'+
    ['all','setup','receipts'].map(function(k){ return '<button type="button" data-act="'+k+'" aria-pressed="'+(state.act===k)+'">'+esc(t('act_'+k))+'</button>'; }).join('')+'</div></div></div><div class="mg-panel__body">'+
    (items?'<ol class="gs-timeline">'+items+'</ol>':'<p class="gs-timeline__empty">'+esc(t('noActivity'))+'</p>')+
    (list.length>6?'<div class="mg-act__more">'+btn(state.actAll?t('showFewerAct'):t('showAllAct',{n:n(list.length)}),{tone:'secondary', outline:true, attrs:'data-actall'})+'</div>':'')+'</div></section>';
}

function lanesHtml(){
  var lanes = [['DRAFT','laneDraft','laneDraftHint'],['ACTIVE','laneActive','laneActiveHint'],['ARCHIVED','laneArchived','laneArchivedHint']];
  var q = state.q.trim().toLowerCase();
  return '<div class="mg-filter mg-panel" style="margin-top:16px;border-radius:var(--mg-r)"><label class="mg-search" style="margin-left:0;max-width:420px"><span class="mg-sr">'+esc(t('search'))+'</span>'+icon('fa-magnifying-glass')+'<input type="search" id="mg-q" value="'+esc(state.q)+'" placeholder="'+esc(t('search'))+'"></label>'+
    (can('create')?'<span style="margin-left:auto">'+btn(t('newCode'),{icon:'fa-plus', href:url('tracking-codes/create')})+'</span>':'')+'</div>'+
    '<div class="mg-lanes">'+lanes.map(function(l){
      var cs = P.codes.filter(function(c){ return c.status===l[0] && (!q || (c.code+' '+c.title+' '+(c.geo||'')).toLowerCase().indexOf(q)>=0); })
        .sort(function(a,b){ return (needsSetup(b)-needsSetup(a)) || a.code.localeCompare(b.code, undefined, {numeric:true}); });
      return '<section class="mg-lane" aria-labelledby="mg-lane-'+l[0]+'"><div class="mg-lane__head">'+badge(l[0]).replace('gs-badge"','gs-badge" aria-hidden="true"')+'<h2 id="mg-lane-'+l[0]+'" class="mg-sr">'+esc(t(l[1]))+'</h2>'+tag(n(cs.length),'secondary')+'</div><p class="mg-lane__hint">'+esc(t(l[2]))+'</p>'+
        (cs.length ? cs.map(function(c){ return cardHtml(c, true); }).join('') : '<div class="mg-lane__empty">'+esc(t('laneEmpty'))+'</div>')+'</section>';
    }).join('')+'</div>';
}

/* ------------------------------------------------------------------ render */
var NOTES = {
 a: [
  ['Design', ['One table of tracking codes. Each row shows its setup checks and the single next action for your role; the rest sits in a "…" menu.', 'Expand a row for its planned products, scope, order document, lifecycle and status changes. Each change says what it does before you confirm.', 'The to-do list and readiness checklist at the top replace the old fact snapshot. Delivery figures stay on Monitor, linked from the line beside the tabs.', 'Team, rules and tools sit in the side rail. Officers see head-only actions disabled with the reason; Support and Monitor get a read-only page.']],
  ['Found in programme 30', ['The programme requires an order PDF for every code, but none of its 4 active codes has one. The fixture created them without it.', 'The current Rules panel always says over-allocation is "advisory". This programme actually blocks it (allow_overshoot = false). This design reads the setting.', 'The codes store specificity_level "CATEGORY", but the current page checks for "2_CATEGORY", so their 12 product targets never appear.', 'The 48 timeline rows are 4 receipts × 12 product lines, so activity is grouped by receipt here. Activation and creation are not logged; this prototype shows them from the code records.']],
  ['To implement', ['Reuse InitiativeController@manage and drop the snapshot aggregate queries.', 'Compute per-code checks on the server: targets present (by plan type), scope present, order PDF when require_documents is set.', 'Group GRN_RECEIVED by GRN reference (store the GRN id in metadata), and log CODE_CREATED, CODE_ACTIVATED, CODE_ARCHIVED and DOCUMENT_ATTACHED.', 'Kit components: page-header, level-switch, status-callout, readiness, role-slot, key-value, action-row, code-segments, status-badge, table, timeline, monitor-lifecycle.']]
 ],
 b: [
  ['Design', ['Lifecycle lanes: Draft, Active and Archived side by side, so the head can see at a glance what is waiting to go live.', 'Each card shows its checks and next action. "Details" opens a side panel with the full plan, scope, documents, lifecycle and status changes.', 'Team, rules and activity sit below the lanes. The same role rules apply as in A.', 'Works best for programmes with a handful of codes. The table in A scales better beyond about 15 codes.']],
  ['Found in programme 30', ['The programme requires an order PDF for every code, but none of its 4 active codes has one.', 'The current Rules panel says over-allocation is advisory, but the programme blocks it.', 'Product targets are hidden today because of the "CATEGORY" vs "2_CATEGORY" mismatch.', 'The 48 activity rows are 4 receipts. Activation is not logged.']],
  ['To implement', ['Same data as A. The drawer is a server-rendered panel that opens without a page load (or a code-page link when JavaScript is off).', 'Lanes stack into one column on phones, with Draft first.']]
 ]
};
function renderNotes(){
  document.getElementById('mg-notes-sum').textContent = state.opt==='a' ? 'About option A · Workbench' : 'About option B · Lifecycle lanes';
  document.getElementById('mg-notes-body').innerHTML = NOTES[state.opt].map(function(g){ return '<div><h4>'+esc(g[0])+'</h4><ul>'+g[1].map(function(x){ return '<li>'+esc(x)+'</li>'; }).join('')+'</ul></div>'; }).join('');
}
function renderControls(){
  var root = document.documentElement;
  [['#mg-opt button','opt',state.opt],['#mg-role button','role',state.role],['#mg-data button','data',state.data],['#mg-lang button','lang',state.lang],['#mg-mode button','mode',root.getAttribute('data-theme')]].forEach(function(x){
    document.querySelectorAll(x[0]).forEach(function(b){ b.setAttribute('aria-pressed', String(b.dataset[x[1]]===x[2])); });
  });
  document.getElementById('mg-skin').value = root.getAttribute('data-skin');
  root.setAttribute('lang', state.lang==='bn'?'bn':'en');
}
function render(){
  renderControls(); renderNotes();
  var page = document.getElementById('mg-page');
  var focusId = document.activeElement && document.activeElement.id;
  var body = headerHtml()+tabsHtml()+bandHtml();
  if(state.opt==='a') body += '<div class="mg-work"><div class="mg-stack">'+codesPanelA()+activityHtml()+'</div><aside class="mg-stack mg-rail">'+teamHtml()+rulesHtml()+toolsHtml()+'</aside></div>';
  else body += lanesHtml()+'<div class="mg-under">'+activityHtml()+teamHtml()+'<div class="mg-stack">'+rulesHtml()+toolsHtml()+'</div></div>';
  page.innerHTML = body;
  if(focusId==='mg-q'){ var q = document.getElementById('mg-q'); q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
  renderDrawer();
}
function renderDrawer(){
  var el = document.getElementById('mg-drawer'), c = state.drawer && codeById(state.drawer);
  if(!c){ el.hidden = true; el.innerHTML = ''; return; }
  el.hidden = false;
  el.innerHTML = '<div class="mg-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="mg-drawer-h"><div class="mg-drawer__head"><div style="min-width:0">'+segs(c)+' '+badge(c.status)+'<h2 id="mg-drawer-h">'+esc(c.title)+'</h2></div>'+
    '<span class="mg-drawer__close">'+btn(t('close'),{tone:'secondary', outline:true, icon:'fa-xmark', attrs:'data-close-drawer'})+'</span></div><div class="mg-drawer__body"><div class="mg-drawer__actions">'+primaryAction(c)+moreMenu(c)+'</div>'+checkChips(c)+detailHtml(c, true)+'</div></div>';
}

/* ------------------------------------------------------------------ actions */
var dialog = document.getElementById('mg-dialog');
function toast(msg){ var el = document.getElementById('mg-toast'); el.textContent = msg; el.hidden = false; clearTimeout(toast._t); toast._t = setTimeout(function(){ el.hidden = true; }, 3600); }
function nowIso(){ return NOW; }
function confirmDialog(c, kind){
  var title, lines, okLabel, okTone, extra = '';
  if(kind==='activate'){ title = t('dlg_activate',{c:short(c)}); lines = [t('dlg_activate1'), t('dlg_activate2',{o:c.offices.length===1?c.offices[0]:officesLabel(c)}), t('dlg_activate3')]; okLabel = t('a_activate'); okTone = 'success'; }
  if(kind==='archive'){ title = t('dlg_archive',{c:short(c)}); lines = [t('dlg_archive1'), t('dlg_archive2',{n:n(c.grns)}), t('dlg_archive3')]; okLabel = t('a_archive'); okTone = 'danger'; }
  if(kind==='delete'){ title = t('dlg_delete',{c:short(c)}); lines = [t('dlg_delete1'), t('dlg_delete2')]; okLabel = t('a_delete'); okTone = 'danger'; }
  if(kind==='attach'){ title = t('dlg_attach',{c:short(c)}); lines = [t('dlg_attach1')]; okLabel = t('attach'); okTone = 'primary';
    extra = '<label>'+esc(t('dlg_attachFile'))+'<input type="file" id="mg-file" accept="application/pdf"></label>'; }
  dialog.innerHTML = '<div class="mg-dialog__body"><h2 id="mg-dlg-h">'+esc(title)+'</h2><ul>'+lines.map(function(l){ return '<li>'+esc(l)+'</li>'; }).join('')+'</ul>'+extra+'</div>'+
    '<div class="mg-dialog__foot"><button type="button" class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" data-dlg="cancel">'+esc(t('cancel'))+'</button><button type="button" class="gs-btn gs-btn--'+okTone+' gs-btn--sm" data-dlg="ok">'+esc(okLabel)+'</button></div>';
  dialog.setAttribute('aria-labelledby', 'mg-dlg-h');
  dialog._confirm = function(){
    var file = document.getElementById('mg-file');
    apply(c, kind, file && file.files[0] ? file.files[0].name : null);
  };
  dialog._opener = document.activeElement;
  dialog.showModal();
}
dialog.addEventListener('click', function(e){
  var b = e.target.closest('[data-dlg]');
  if(!b) return;
  var confirmFn = b.dataset.dlg==='ok' ? dialog._confirm : null, opener = dialog._opener;
  dialog.close();
  if(confirmFn) confirmFn();
  else if(opener && document.contains(opener)) opener.focus();
});
function apply(c, kind, fileName){
  var at = nowIso();
  if(kind==='activate'){ c.status = 'ACTIVE'; c.activated = at; P.activity.push({type:'activated', at:at, by:me(), target:c.code}); toast(t('toast_activated',{c:short(c)})); }
  if(kind==='archive'){ c.status = 'ARCHIVED'; c.archived = at; P.activity.push({type:'archived', at:at, by:me(), target:c.code}); toast(t('toast_archived',{c:short(c)})); }
  if(kind==='delete'){ P.codes = P.codes.filter(function(x){ return x!==c; }); P.activity.push({type:'deleted', at:at, by:me(), target:c.code}); if(state.drawer===c.id) state.drawer = null; toast(t('toast_deleted',{c:short(c)})); }
  if(kind==='attach'){ c.docs = [{name:fileName || ('order-'+short(c)+'.pdf'), at:at, by:me()}]; P.activity.push({type:'doc', at:at, by:me(), target:c.code}); toast(t('toast_attached',{c:short(c)})); }
  render();
}

document.addEventListener('click', function(e){
  var el;
  if((el = e.target.closest('[data-toggle]'))){ var id = +el.dataset.toggle; state.open[id] = !state.open[id]; render(); var b = document.querySelector('[data-toggle="'+id+'"]'); if(b) b.focus(); return; }
  if((el = e.target.closest('[data-tab]'))){ state.tab = el.dataset.tab; render(); return; }
  if((el = e.target.closest('[data-act]'))){ state.act = el.dataset.act; state.actAll = false; render(); return; }
  if((el = e.target.closest('[data-actall]'))){ state.actAll = !state.actAll; render(); return; }
  if((el = e.target.closest('[data-go]'))){
    if(el.dataset.go==='setup'){ state.opt==='a' ? (state.tab = 'setup') : null; render(); var tgt = document.getElementById(state.opt==='a'?'mg-codes-h':'mg-lane-ACTIVE'); if(tgt) tgt.scrollIntoView({behavior:'smooth', block:'start'}); }
    if(el.dataset.go==='team'){ var tm = document.getElementById('mg-team'); if(tm){ tm.scrollIntoView({behavior:'smooth', block:'start'}); tm.focus({preventScroll:true}); } }
    return;
  }
  if((el = e.target.closest('[data-drawer]'))){ state.drawer = +el.dataset.drawer; renderDrawer(); var cl = document.querySelector('[data-close-drawer]'); if(cl) cl.focus(); return; }
  if((el = e.target.closest('[data-close-drawer]')) || e.target.id==='mg-drawer'){ var back = state.drawer; state.drawer = null; renderDrawer(); var opener = document.querySelector('[data-drawer="'+back+'"]'); if(opener) opener.focus(); return; }
  if((el = e.target.closest('[data-cmd]'))){
    var c = codeById(+el.dataset.id); if(!c) return;
    var det = el.closest('details'); if(det) det.open = false;
    if(el.dataset.cmd==='edit'){ toast(t('toast_link',{w:t('a_edit')})); return; }
    confirmDialog(c, el.dataset.cmd); return;
  }
  if((el = e.target.closest('a[data-link]'))){ e.preventDefault(); toast(t('toast_link',{w:el.dataset.link})); return; }
  if(!e.target.closest('.mg-more')) document.querySelectorAll('.mg-more[open]').forEach(function(m){ m.open = false; });
});
document.addEventListener('keydown', function(e){
  if(e.key==='Escape'){
    var openMenu = document.querySelector('.mg-more[open]');
    if(openMenu){ openMenu.open = false; openMenu.querySelector('summary').focus(); return; }
    if(state.drawer){ var back = state.drawer; state.drawer = null; renderDrawer(); var opener = document.querySelector('[data-drawer="'+back+'"]'); if(opener) opener.focus(); }
  }
});
document.addEventListener('input', function(e){ if(e.target.id==='mg-q'){ state.q = e.target.value; render(); } });

document.getElementById('mg-opt').addEventListener('click', function(e){ var b = e.target.closest('button'); if(!b) return; state.opt = b.dataset.opt; state.drawer = null; save(); render(); });
document.getElementById('mg-role').addEventListener('click', function(e){ var b = e.target.closest('button'); if(!b) return; state.role = b.dataset.role; save(); render(); });
document.getElementById('mg-data').addEventListener('click', function(e){ var b = e.target.closest('button'); if(!b) return; state.data = b.dataset.data; P = DATASETS[state.data](); state.open = {}; state.drawer = null; state.tab = 'all'; state.q = ''; save(); render(); });
document.getElementById('mg-lang').addEventListener('click', function(e){ var b = e.target.closest('button'); if(!b) return; state.lang = b.dataset.lang; save(); render(); });
document.getElementById('mg-mode').addEventListener('click', function(e){ var b = e.target.closest('button'); if(!b) return; document.documentElement.setAttribute('data-theme', b.dataset.mode); render(); });
document.getElementById('mg-skin').addEventListener('change', function(e){ document.documentElement.setAttribute('data-skin', e.target.value); try{ localStorage.setItem('mg-skin', e.target.value); }catch(x){} });
document.getElementById('mg-reset').addEventListener('click', function(){ P = DATASETS[state.data](); state.open = {}; state.drawer = null; state.tab = 'all'; state.q = ''; state.act = 'all'; state.actAll = false; render(); });

render();
})();
