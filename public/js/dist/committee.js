/* Committee tasks use Bootstrap 3, scoped JSON routes and server authorization. */
(function ($) {
    'use strict';
    let pending = null, people = [], busy = false, initializing=false, activeRequest = null, saveTimer, intakeTimer, previewTimer, lookupTimer;
    function root() { return $('.committee-workspace'); }
    function base() { return root().attr('data-base'); }
    function copy(key) { return (root().data('copy') || {})[key] || key; }
    function feedback(message, success, drawer) {
        const area = drawer ? $('.cm-person-feedback') : $('.cm-feedback');
        area.removeClass('hidden alert-success alert-danger').addClass(success ? 'alert-success' : 'alert-danger').text(message);
        if (!drawer && !success) area.trigger('focus');
    }
    function errorMessage(xhr) {
        const body = xhr.responseJSON || {};
        if (xhr.status === 409) return copy('conflict');
        if (xhr.status >= 500 || !xhr.status) return copy('failed') + (body.reference ? ' · ' + body.reference : '');
        return Object.values(body.errors || {}).flat().join(' ') || body.error || body.reason || body.message || copy('failed');
    }
    function options(select, rows, prompt) {
        const selected = select.val();
        select.empty().append($('<option>').val('').text(prompt || copy('choose')));
        rows.forEach(row => select.append($('<option>').val(row.id).text(row.text + (row.designation ? ' · ' + row.designation : ''))));
        if (selected) select.val(selected);
    }
    function init() {
        initializing=true;
        if ($('.cm-people,.cm-external,.cm-transfer-person').length) {
            $.getJSON(base() + '/api/people').done(data => {
                people = data.people;
                $('.cm-people').each(function () { options($(this), data.people); });
                $('.cm-external').each(function () { options($(this), data.external); });
            }).fail(xhr => feedback(errorMessage(xhr), false));
        }
        if ($('.cm-transfer-person').length) $.getJSON(base()+'/api/people',{transfers:1}).done(data=>options($('.cm-transfer-person'),data.people)).fail(xhr=>feedback(errorMessage(xhr),false));
        $('.cm-intake').each(function() {
            const form=$(this), intake=JSON.parse(form.find('.cm-intake-data').text());
            Object.entries(intake.fields || {}).forEach(([name,value])=>{
                const input=form.find('[name="'+name+'"]');
                if(input.is(':radio'))input.filter('[value="'+value+'"]').prop('checked',true);else input.val(value);
            });
            if(intake.has_file) {
                form.find('[name=file]').prop('required',false).after($('<p class="cm-muted">').text(copy('saved_attachment')));
            }
        });
        $('.cm-scope-type,.cm-term-basis').trigger('change');
        $('#cm-person-drawer .tab-pane:not(.active) :input').prop('disabled', true);
        $('.cm-dissolve-submit').each(function () { $(this).attr('data-authorized', this.disabled ? '0' : '1').prop('disabled', true); });
        $('.cm-transfer-submit').prop('disabled', true);
        $('.cm-person-form .cm-post-fields :input').prop('disabled', true);
        // Existing draft types are fixed once seats or coverage have been composed.
        $('.cm-autosave .cm-type-choice').prop('disabled', true);
        initializing=false;
    }
    function refresh(message) {
        const focused = document.activeElement && document.activeElement.name;
        $.get(window.location.href).done(html => {
            const workspace = $(html).find('.committee-workspace');
            if (workspace.length) {
                $('.modal').modal('hide'); root().replaceWith(workspace); init();
                if (message) feedback(message, true);
                if (focused) root().find('[name="'+focused.replace(/"/g,'')+'"]').first().trigger('focus');
            }
        }).fail(xhr => feedback(errorMessage(xhr), false));
    }
    function request(form, extra, url) {
        const data = new FormData(form);
        if ($(form).data('method')) data.set('_method', $(form).data('method'));
        $(form).find(':disabled[name]:not([type=submit])').each(function () {
            if ($(this).hasClass('cm-type-choice')) data.set(this.name,this.value);
        });
        Object.entries(extra || {}).forEach(([key, value]) => data.set(key, value));
        $(form).find('.cm-transfer-replacement').each(function(){if(this.value==='vacant')data.set(this.name,'');});
        return $.ajax({url:url || form.action,method:'POST',data,processData:false,contentType:false,headers:{Accept:'application/json'}});
    }
    function proposalSummary(value, container) {
        const names = root().data('copy') || {}, labels = root().data('options') || {};
        Object.entries(value || {}).forEach(([key, content]) => {
            if (key === '_method' || key === 'scope') return;
            const label = names[key] || $('.cm-catalog [name="'+key+'"]:first').closest('.form-group').find('label').text();
            if (content && typeof content === 'object') {
                const box = $('<div class="cm-subtle">'); if (label) box.append($('<strong>').text(label));
                proposalSummary(content, box); container.append(box);
            } else if (label || typeof content === 'number') container.append($('<p>').text((label ? label + ': ' : '') + (labels[content] || content)));
        });
    }
    function send(form, extra, autosave) {
        if (busy) return activeRequest.then(()=>send(form,extra,autosave)); busy = true;
        const snapshot=$(form).serialize();
        const buttons = $(form).find(':submit').filter(':enabled'); buttons.prop('disabled', true);
        if (autosave) $('.cm-draft-save').text(copy('saving'));
        activeRequest=request(form, extra).done(result => {
            if (result.review_required) {
                pending = {form,review:result};
                $('#cm-confirm-title').text(copy('national_review'));$('#cm-confirm .cm-backdate-help').hide();
                const summary = $('#cm-confirm .cm-confirm-summary').empty().append($('<p>').text(copy('national_review')));
                proposalSummary(result.proposal,summary);
                summary.append($('<label>').text('CHANGE').append($('<input class="form-control cm-review-confirmation" required>')));
                $('#cm-confirm').modal('show'); return;
            }
            if (autosave) {
                $(form).find('[name=lock_version]').val(result.lock_version);
                // A later edit remains dirty even if the earlier request succeeded.
                const updated=$(form).serialize().replace(/lock_version=\d+/,'lock_version=');
                $(form).attr('data-dirty',updated===snapshot.replace(/lock_version=\d+/,'lock_version=')?'false':'true');
                $('.cm-draft-save').text(copy($(form).attr('data-dirty')==='true'?'changed':'saved')); return;
            }
            if (result.url && (/\/drafts$|\/reconstitute$|\/activate$|\/dissolve$/.test(form.action))) { window.location.assign(result.url); return; }
            refresh(result.message || copy('saved'));
        }).fail(xhr => {
            feedback(errorMessage(xhr),false,$(form).hasClass('cm-person-form'));
            if (autosave && xhr.status === 409) {
                $(form).attr('data-conflicted','true');
                $('.cm-draft-save').text(copy('conflict')).append($('<button class="btn btn-default cm-reload" type="button">').text(copy('reload')));
            }
        }).always(() => {busy = false; buttons.prop('disabled',false);});
        return activeRequest;
    }
    function persistIntake(form) {
        clearTimeout(intakeTimer);
        if(form.attr('data-conflicted'))return $.Deferred().reject().promise();
        if(busy)return activeRequest.then(()=>persistIntake(form));
        busy=true; const snapshot=form.serialize(), file=form.find('[name=file]')[0], selectedFile=file.files[0]; $('.cm-draft-save').text(copy('saving'));
        activeRequest=request(form[0],null,base()+'/order-intake').done(result=>{
            form.find('[name=intake_revision]').val(result.revision);
            $('.cm-discard-intake [name=intake_revision]').val(result.revision);
            const sameFile=file.files[0]===selectedFile;
            if(result.has_file) {form.find('[name=file]').prop('required',false);if(sameFile)file.value='';if(!form.find('.cm-attachment-saved').length)$(file).after($('<p class="cm-muted cm-attachment-saved">').text(copy('saved_attachment')));}
            const clean=sameFile && form.serialize().replace(/intake_revision=\d+/,'intake_revision=')===snapshot.replace(/intake_revision=\d+/,'intake_revision=');
            form.attr('data-dirty',clean?'false':'true');$('.cm-draft-save').text(copy(clean?'saved':'changed'));
        }).fail(xhr=>{feedback(errorMessage(xhr),false);if(xhr.status===409)form.attr('data-conflicted','true');})
            .always(()=>{busy=false;});
        return activeRequest;
    }
    function flushDraft(form) {
        clearTimeout(saveTimer);clearTimeout(intakeTimer);
        if(busy)return activeRequest.then(()=>flushDraft(form));
        if(form.attr('data-conflicted'))return $.Deferred().reject().promise();
        if(form.attr('data-dirty')!=='true')return $.Deferred().resolve().promise();
        if(form.hasClass('cm-intake'))return persistIntake(form).then(()=>flushDraft(form));
        if(!form[0].reportValidity())return $.Deferred().reject().promise();
        return send(form[0],null,true).then(()=>flushDraft(form));
    }
    function confirm(form) {
        pending = {form};
        const summary = $('#cm-confirm .cm-confirm-summary').empty();
        $('#cm-confirm-title').text($(form).find(':submit').text());
        root().find('h1').first().clone().appendTo(summary);
        if($(form).hasClass('cm-discard-intake'))summary.append($('<p>').text($('.cm-intake [name=memo_no]').val()));
        $(form).find('input:not([type=hidden]):not([type=file]),textarea,select').filter(':enabled').each(function () {
            if ($(this).is('[type=checkbox],[type=radio]') && !this.checked) return;
            const value = $(this).is('select') ? $(this).find(':selected').text() : this.value;
            const label = $(this).closest('.form-group').find('label').text();
            if (value && label) summary.append($('<p>').text(label + ': ' + value));
        });
        $('.cm-preview').first().clone().appendTo(summary);
        const committee = root().attr('data-committee'), date = $(form).find('[name=from_date],[name=effective_from],[name=date],[name=to_date]').first().val();
        $('#cm-confirm .cm-backdate-help').toggle(Boolean(committee && date && date<root().attr('data-today')));
        if (committee && date && date < root().attr('data-today')) {
            $.getJSON(base()+'/api/'+committee+'/impact',{as_of:date}).done(data => {
                (data.snapshot_usage || []).forEach(usage => summary.append($('<p>').text(String(usage.count || 0)+' · '+usage.label)));
            }).fail(xhr => { pending=null; $('#cm-confirm').modal('hide'); feedback(errorMessage(xhr),false); });
        }
        $('#cm-confirm').modal('show');
    }
    $(document).on('submit','.cm-command',function(event) {
        event.preventDefault(); clearTimeout(saveTimer);clearTimeout(intakeTimer);
        if (!this.checkValidity()) {this.reportValidity();return;}
        if ($(this).data('confirm')) confirm(this);
        else if($(this).hasClass('cm-intake')) {const form=this;flushDraft($(form)).done(()=>send(form));}
        else send(this,null,$(this).hasClass('cm-autosave'));
    });
    $(document).on('click','.cm-confirm-send',function() {
        if (!pending || busy) return;
        const extra = pending.review ? {review_token:pending.review.review_token,confirmation:$('.cm-review-confirmation').val()} : {};
        if (pending.review && extra.confirmation !== 'CHANGE') return;
        const form=pending.form; pending=null; $('#cm-confirm').modal('hide'); send(form,extra);
    });
    $(document).on('click','button[href]',function() {if(!this.disabled) window.location.assign($(this).attr('href'));});
    $(document).on('click','.cm-reload',function(){window.location.reload();});
    $(document).on('click','.cm-print',function(){window.print();});
    $(document).on('input change','.cm-autosave :input',function() {
        if(initializing)return;
        const form=$(this).closest('form')[0]; clearTimeout(saveTimer);
        $(form).attr('data-dirty','true');
        $('.cm-draft-save').text(copy('changed'));
        saveTimer=setTimeout(() => {
            if ($(form).attr('data-conflicted') || !form.checkValidity()) return;
            if (busy) {$(form).find('[name=name_en]').trigger('change');return;}
            send(form,null,true);
        },800);
    });
    $(document).on('input change','.cm-intake :input',function(){if(initializing)return;const form=$(this).closest('form');form.attr('data-dirty','true');clearTimeout(intakeTimer);intakeTimer=setTimeout(()=>persistIntake(form),800);});
    $(document).on('click','.cm-keep-intake',function(){const form=$(this).closest('form').attr('data-dirty','true');flushDraft(form).done(()=>window.location.assign(base()));});
    $(document).on('click','.committee-workspace a[href]',function(event){
        const form=root().find('.cm-autosave,.cm-intake').first();if(!form.length || (form.attr('data-dirty')!=='true'&&!busy) || $(this).is('[data-toggle]'))return;
        event.preventDefault();const url=this.href;flushDraft(form).done(()=>window.location.assign(url));
    });
    window.addEventListener('beforeunload',function(event){if(root().find('[data-dirty=true]').length){event.preventDefault();event.returnValue='';}});
    $(document).on('change','.cm-scope-type',function() {
        const select=$(this).closest('form').find('.cm-scope-id');
        $.getJSON(base()+'/api/scopes/'+this.value).done(data=>options(select,data.results)).fail(xhr=>feedback(errorMessage(xhr),false));
    });
    $(document).on('change','.cm-type-choice',function() {
        const form=$(this).closest('form');
        const types=JSON.parse(form.find('.cm-types-data').text() || '[]'), type=types.find(t=>String(t.id)===this.value);
        if(type && !form.hasClass('cm-autosave')) {if(!form.find('[name=name_bn]').val())form.find('[name=name_bn]').val(type.name_bn);if(!form.find('[name=name_en]').val())form.find('[name=name_en]').val(type.name_en);if(!form.attr('data-term-chosen'))form.find('[name=term_basis]').val(type.term).trigger('change');}
    });
    $(document).on('change','.cm-term-basis',function() {
        const form=$(this).closest('form'), end=form.find('[name=effective_to]');
        end.prop('required',['FIXED','SINGLE_MATTER'].includes(this.value));
        if(this.value==='UNTIL_FURTHER_ORDER') end.val('');
        if(this.value==='FISCAL_YEAR') {const from=form.find('[name=effective_from]').val();if(from) {const d=new Date(from+'T12:00:00');end.val((d.getMonth()>=6 ? d.getFullYear()+1 : d.getFullYear())+'-06-30');}}
    });
    $(document).on('click','.cm-order-next',function() {
        const button=this, intake=$(this).closest('form');
        if(intake.hasClass('cm-intake') && (intake.attr('data-dirty')==='true'||busy)) {flushDraft(intake).done(()=>$(button).trigger('click'));return;}
        const form=$(this).closest('form'), invalid=form.find('.cm-order-first :input[required]').filter(function(){return !this.checkValidity();}).first();
        if(invalid.length){invalid[0].reportValidity();return;}
        const job=form.find('[name=job]:checked').val();
        if(job!=='form' && job!=='reconstitute') {
            form.find('.cm-job-target').removeClass('hidden'); return;
        }
        if(job==='reconstitute' && !form.find('[name=target_committee]').val() && !/\/reconstitute$/.test(form[0].action)) {form.find('.cm-job-target').removeClass('hidden'); return;}
        form.find('.cm-order-first').addClass('cm-hidden');form.find('.cm-order-second').removeClass('cm-hidden');
        form.find('.cm-type-choice').trigger('change');root().find('.cm-stepper li').removeAttr('aria-current').eq(1).attr('aria-current','step');
        form.find('.cm-order-second :input').first().trigger('focus');
    });
    $(document).on('click','.cm-order-back',function() {const form=$(this).closest('form');form.find('.cm-order-first').removeClass('cm-hidden');form.find('.cm-order-second').addClass('cm-hidden');});
    $(document).on('click','.cm-record-job',function() {
        const buttonElement=this, intake=$(this).closest('form');
        if(intake.hasClass('cm-intake') && (intake.attr('data-dirty')==='true'||busy)) {flushDraft(intake).done(()=>$(buttonElement).trigger('click'));return;}
        const form=$(this).closest('form'), target=form.find('[name=target_committee]').val(), job=form.find('[name=job]:checked').val();if(!target)return;
        if(job==='reconstitute') {form[0].action=base()+'/'+target+'/reconstitute';form.find('.cm-order-next').trigger('click');return;}
        const data=new FormData(form[0]);data.set('kind',({replace:'AMENDMENT',extend:'EXTENSION',suspend:'SUSPENSION',resume:'RESUMPTION',dissolve:'DISSOLUTION',correct:'CORRIGENDUM'})[job]);
        if(busy)return;busy=true;$(this).prop('disabled',true);const button=$(this);
        activeRequest=$.ajax({url:base()+'/'+target+'/orders',method:'POST',data,processData:false,contentType:false,headers:{Accept:'application/json'}}).done(()=>{form.attr('data-dirty','false');window.location.assign(base()+'/'+target+'?action='+job);}).fail(xhr=>feedback(errorMessage(xhr),false)).always(()=>{busy=false;button.prop('disabled',false);});
    });
    $(document).on('change','.cm-dissolve-number',function() {const button=$('.cm-dissolve-submit');button.prop('disabled',button.attr('data-authorized')!=='1'||this.value!==button.attr('data-number'));});
    $(document).on('input','.cm-dissolve-number',function(){$(this).trigger('change');});
    $(document).on('click','.cm-history-filter',function() {
        const filter=$(this).data('filter');$('.cm-history-filter').attr('aria-pressed','false');$(this).attr('aria-pressed','true');
        $('.cm-history-entry').each(function(){$(this).toggle(filter==='all'||$(this).data('group')===filter);});
        $('.cm-timeline>li').each(function(){$(this).toggle($(this).find('.cm-history-entry').toArray().some(entry=>filter==='all'||$(entry).data('group')===filter));});
        $('.cm-history-empty').toggleClass('hidden',$('.cm-history-entry:visible').length>0);
    });
    $(document).on('click','.cm-open-person',function() {
        const form=$('.cm-person-form'), command=$(this).data('command');
        form[0].reset(); form.find('[name=person_source]').val('office');
        form.attr('action',base()+'/'+root().attr('data-committee')+'/seats'+(command==='new'?'':'/'+$(this).data('seat')+'/'+command));
        form.find('.cm-new-seat-fields').toggle(command==='new').find(':input').prop('disabled',command!=='new');
        form.attr('data-fixed-kind',command==='new'?'':$(this).attr('data-holder-kind'));
        form.find('.cm-by-post').trigger('change');form.find('.cm-code-person,.cm-person-feedback').addClass('hidden');
        const fixed=form.attr('data-fixed-kind');
        $('#cm-person-drawer [data-source]').each(function(){$(this).parent().toggle(!fixed || (fixed==='EXTERNAL'?$(this).data('source')==='external':$(this).data('source')!=='external'));});
        $('#cm-person-drawer [data-source='+(fixed==='EXTERNAL'?'external':'office')+']').tab('show');$('#cm-person-drawer').modal('show');
    });
    $(document).on('shown.bs.tab','#cm-person-drawer [data-toggle=tab]',function() {
        const form=$('.cm-person-form'), source=$(this).data('source');
        form.find('.tab-pane :input').prop('disabled',true);form.find('.tab-pane.active :input').prop('disabled',false);
        form.find('[name=person_source]').val(source);form.find('[name=holder_kind]').val(source==='external'?'EXTERNAL':(form.attr('data-fixed-kind') || (form.find('.cm-by-post').prop('checked')?'POST':'PERSON')));
        form.find('.cm-by-post-label').toggle(source!=='external');
        $('#cm-person-drawer [role=tab]').attr('aria-selected','false');$(this).attr('aria-selected','true');
    });
    $(document).on('change','.cm-by-post',function() {
        const form=$(this).closest('form');form.find('[name=holder_kind]').val(this.checked?'POST':'PERSON');form.find('.cm-post-fields').toggleClass('hidden',!this.checked).find(':input').prop('disabled',!this.checked).prop('required',this.checked);
    });
    $(document).on('input','.cm-people-search',function() {
        clearTimeout(lookupTimer);const q=this.value;
        lookupTimer=setTimeout(()=>$.getJSON(base()+'/api/people',{q}).done(data=>options($('#cm-person-office .cm-people'),data.people)),250);
    });
    function lookup(form, drawer) {
        const code=form.find('[name=verification_code]').val();
        $.ajax({url:base()+'/api/people/by-code',method:'POST',data:{code,_token:form.find('[name=_token]').val()},headers:{Accept:'application/json'}}).done(person=>{
            const name=(root().attr('data-locale')==='bn-BD'?person.name_bn:person.name_en)+' · '+person.designation_en+' · '+person.office_name;
            if(drawer) {form.find('.cm-code-user').val(person.user_id);$('.cm-code-person').removeClass('hidden').empty().append($('<strong>').text(copy('is_right_person')),$('<p>').text(name));}
            else {form.find('[name=user_id]').append($('<option>').val(person.user_id).text(name)).val(person.user_id).trigger('change');}
        }).fail(xhr=>feedback(errorMessage(xhr),false,drawer));
    }
    $(document).on('click','.cm-lookup-person',function(){lookup($(this).closest('form'),true);});
    $(document).on('input','.cm-person-form [name=verification_code]',function(){$(this).closest('form').find('.cm-code-user').val('');$('.cm-code-person').addClass('hidden');});
    $(document).on('click','.cm-change-lookup',function(){lookup($(this).closest('form'),false);});
    $(document).on('submit','.cm-person-form',function(event) {event.preventDefault();if(this.reportValidity())send(this);});
    $(document).on('change input','.cm-member-change :input',function() {
        clearTimeout(previewTimer); const form=$(this).closest('form');
        previewTimer=setTimeout(()=>preview(form),400);
    });
    function preview(form) {
        const data=new FormData(form[0]);data.set('operation',form.attr('data-operation'));data.set('seat_id',form.attr('data-seat'));data.set('tenure_id',form.attr('data-tenure') || '');
        if(!data.get('order_id') || (!data.get('user_id') && !data.get('external_member_id') && data.get('operation')!=='release'))return;
        $.ajax({url:base()+'/'+root().attr('data-committee')+'/preview',method:'POST',data,processData:false,contentType:false,headers:{Accept:'application/json'}}).done(result=>{
            const container=form.find('.cm-preview').empty(), findings=result.findings || [], block=findings.some(f=>f.severity==='BLOCK');
            container.append($('<h3>').text(copy(block?'preview_cannot':findings.length?'preview_attention':'preview_ready')));
            (result.view.seats || []).forEach(seat=>container.append($('<p>').text((seat.holder ? (root().attr('data-locale')==='bn-BD'?seat.holder.nameBn:seat.holder.nameEn) : copy('vacant')))));
            const acknowledgements=form.find('.cm-preview-acknowledgements').empty();
            findings.forEach(f=>{
                const text=root().attr('data-locale')==='bn-BD'?f.message_bn:f.message_en;
                container.append($('<p>').text(text));
                if(f.severity==='WARN')acknowledgements.append($('<label>').append($('<input type="checkbox" name="acknowledged[]">').val(f.code),document.createTextNode(text)));
            });
            form.find('.cm-inoperable-ack').toggleClass('hidden',!block).find(':input').prop('disabled',!block);
        }).fail(xhr=>form.find('.cm-preview').text(errorMessage(xhr)));
    }
    $(document).on('change','.cm-transfer-person',function() {
        transferPreviewVersion++;
        const user=Number(this.value), container=$('.cm-transfer-seats').empty().text(copy('loading'));$('.cm-transfer-submit').prop('disabled',true);
        if(!user){container.empty();return;}
        $.getJSON(base()+'/api/transfers',{user_id:user}).done(data=>{
            container.empty();if(!data.seats.length)container.text(copy('no_transfer_seats'));
            data.seats.forEach((row,index)=>{
                const section=$('<fieldset class="cm-subtle">').append($('<legend>').text(row.committee_name+' · '+row.role));
                const prefix='changes['+index+']';
                ['committee_id','seat_id'].forEach(key=>section.append($('<input type="hidden">').attr({name:prefix+'['+key+']',value:row[key]})));
                const dateId='cm-transfer-date-'+index, personId='cm-transfer-replacement-'+index;
                section.append($('<label>').attr('for',dateId).text(copy('since').replace(':date','')),$('<input type="date" class="form-control" required>').attr({id:dateId,name:prefix+'[from_date]',value:row.from_date}));
                const replacement=$('<select class="form-control cm-transfer-replacement" required>').attr({name:prefix+'[user_id]',id:personId});options(replacement,people.filter(p=>Number(p.id)!==user));replacement.append($('<option value="vacant">').text(copy('leave_vacant')));
                section.append($('<label>').attr('for',personId).text(copy('replacement')),replacement,$('<input type="hidden">').attr({name:prefix+'[leave_vacant]',value:0}));
                section.attr('data-committee',row.committee_id).attr('data-seat',row.seat_id).append($('<div class="cm-transfer-preview cm-preview" aria-live="polite">').text(copy('preview_help')));
                container.append(section);
            });
            (data.other_seats || []).forEach(row=>{
                const note=copy('notify_note').replace(':person',row.name).replace(':committee',row.committee_name);
                container.append($('<section class="cm-subtle">').append($('<h3>').text(row.committee_name+' · '+row.office),$('<p>').text(copy('other_office_help')),$('<button type="button" class="btn btn-default cm-copy-note">').attr('data-note',note).text(copy('copy_note'))));
            });
            $('.cm-transfer-submit').prop('disabled',data.seats.length===0);
        }).fail(xhr=>feedback(errorMessage(xhr),false));
    });
    $(document).on('change','.cm-transfer-replacement',function(){const vacant=this.value==='vacant';$(this).closest('fieldset').find('[name$="[leave_vacant]"]').val(vacant?1:0);});
    $(document).on('change input','.cm-transfer-form :input',function(){clearTimeout(previewTimer);previewTimer=setTimeout(transferPreviews,400);});
    let transferPreviewVersion=0, ruleTimer;
    $(document).on('input change','.cm-type-rules :input',function(){const form=$(this).closest('form');clearTimeout(ruleTimer);ruleTimer=setTimeout(()=>ruleImpact(form),500);});
    function ruleImpact(form) {
        if(!form.attr('data-type'))return;
        const data=new FormData(form[0]);data.delete('_method');data.set('type_id',form.attr('data-type'));
        $.ajax({url:base()+'/admin/types/impact',method:'POST',data,processData:false,contentType:false,headers:{Accept:'application/json'}}).done(result=>form.find('.cm-rule-impact').text(result.message)).fail(xhr=>form.find('.cm-rule-impact').text(errorMessage(xhr)));
    }
    function transferPreviews() {
        const version=++transferPreviewVersion, form=$('.cm-transfer-form'), issued=form.find('[name=issued_on]').val();
        if(!issued)return;
        const checks=[];
        form.find('.cm-transfer-seats fieldset').each(function(){
            const section=$(this), replacement=section.find('.cm-transfer-replacement').val(), from=section.find('[type=date]').val();if(!replacement||!from)return;
            const vacant=replacement==='vacant', previous=new Date(from+'T12:00:00');previous.setDate(previous.getDate()-1);
            const last=previous.getFullYear()+'-'+String(previous.getMonth()+1).padStart(2,'0')+'-'+String(previous.getDate()).padStart(2,'0');
            checks.push($.ajax({url:base()+'/'+section.attr('data-committee')+'/preview',method:'POST',headers:{Accept:'application/json'},data:{
                _token:form.find('[name=_token]').val(),operation:vacant?'release':'replace',seat_id:section.attr('data-seat'),transferred_user_id:form.find('[name=user_id]').val(),
                user_id:vacant?'':replacement,from_date:from,to_date:vacant?last:'',issued_on:issued,release_reason:'TRANSFER'
            }}).done(result=>{if(version!==transferPreviewVersion)return;const area=section.find('.cm-transfer-preview').empty();area.append($('<strong>').text(copy(result.findings.some(f=>f.severity==='BLOCK')?'preview_cannot':result.findings.length?'preview_attention':'preview_ready')));result.findings.forEach(f=>area.append($('<p>').text(root().attr('data-locale')==='bn-BD'?f.message_bn:f.message_en)));section.data('findings',result.findings);})
                .fail(xhr=>{if(version===transferPreviewVersion){section.find('.cm-transfer-preview').text(errorMessage(xhr));section.data('findings',null);}}));
        });
        $.when.apply($,checks).always(()=>{
            if(version!==transferPreviewVersion)return;
            const ack=form.find('.cm-preview-acknowledgements').empty(), all=new Map();
            form.find('.cm-transfer-seats fieldset').each(function(){($(this).data('findings') || []).forEach(f=>all.set(f.code,f));});
            all.forEach(f=>{if(f.severity==='WARN')ack.append($('<label>').append($('<input type="checkbox" name="acknowledged[]">').val(f.code),document.createTextNode(root().attr('data-locale')==='bn-BD'?f.message_bn:f.message_en)));});
            const block=Array.from(all.values()).some(f=>f.severity==='BLOCK');form.find('.cm-inoperable-ack').toggleClass('hidden',!block).find(':input').prop('disabled',!block);
        });
    }
    $(document).on('click','.cm-copy-note',function(){navigator.clipboard.writeText($(this).attr('data-note')).catch(()=>feedback(copy('failed'),false));});
    $(init);
})(window.jQuery);
