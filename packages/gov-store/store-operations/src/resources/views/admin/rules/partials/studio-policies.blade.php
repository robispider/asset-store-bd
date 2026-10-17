{{-- Policy library: start a policy from a template, open drafts and published policies, recent attachments. --}}
<section class="rst-panel" id="new-policy" aria-labelledby="rst-new-title">
    <div class="rst-ph"><h2 id="rst-new-title">{{ __('storeops::storeops.rules_ui.create_new_business_rule') }}</h2></div>
    <div class="rst-minis">
        @foreach (['hardware' => 'fa-laptop', 'consumable' => 'fa-tint', 'blank' => 'fa-file'] as $template => $icon)
            <a class="rst-card rst-gs-plain" href="{{ route('storeops.admin.rules.policies.create', $template) }}">
                <span class="rst-card__name"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ __('storeops::studio.templates.'.$template) }}</span>
                <span class="rst-card__pol">{{ __('storeops::studio.templates.'.$template.'_help') }}</span>
            </a>
        @endforeach
    </div>
</section>

@foreach (['drafts' => $draftProfiles, 'published' => $publishedProfiles] as $group => $profiles)
    <section class="rst-panel" aria-labelledby="rst-{{ $group }}-title">
        <div class="rst-ph"><h2 id="rst-{{ $group }}-title">{{ __('storeops::studio.library.'.$group) }} · {{ $profiles->count() }}</h2></div>
        @if ($profiles->isEmpty())
            <p class="rst-context rst-muted">{{ __('storeops::studio.library.empty') }}</p>
        @else
            <div class="gs-table-wrap">
                <table class="gs-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('storeops::storeops.rules_ui.policy_name') }}</th>
                            <th scope="col">{{ __('storeops::storeops.rules_ui.version') }}</th>
                            <th scope="col" class="text-right">{{ __('storeops::storeops.rules_ui.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($profiles as $profile)
                            <tr>
                                <td><strong>{{ $profile->name }}</strong></td>
                                <td>v{{ $profile->version ?? '1.0' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('storeops.admin.rules.policies.edit', $profile->id) }}" class="gs-btn gs-btn--sm">{{ __('storeops::storeops.rules_ui.open_builder') }}</a>
                                    @if ($canPublish && $group === 'published')
                                        <form action="{{ route('storeops.admin.rules.policies.duplicate', $profile->id) }}" method="POST" class="rst-inline-form">
                                            @csrf
                                            <button type="submit" class="gs-btn gs-btn--sm gs-btn--ghost">{{ __('storeops::studio.make_draft') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endforeach

<section class="rst-panel" aria-labelledby="rst-activity-title">
    <div class="rst-ph"><h2 id="rst-activity-title">{{ __('storeops::storeops.rules_ui.recent_gpo_alignment_changes') }}</h2></div>
    <ul class="rst-eff">
        @forelse ($recentActivity as $activity)
            <li>
                <span>{{ __('storeops::studio.activity', ['policy' => $activity['policy'], 'target' => $activity['target']]) }}</span>
                <span class="rst-muted rst-small">{{ $activity['when'] }}</span>
            </li>
        @empty
            <li class="rst-muted">{{ __('storeops::storeops.rules_ui.no_recent_assignment_changes_recorded') }}</li>
        @endforelse
    </ul>
</section>
