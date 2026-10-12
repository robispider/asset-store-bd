

<div class="sim-container">

    <!-- LEFT PANE: What the Storekeeper Sees -->
    <div class="sim-left">
        <div class="sim-header"><i class="fa fa-desktop"></i> {{ __('storeops::storeops.rules_ui.mock_storekeeper_view') }}</div>

        <!-- Standard Item Info -->
        <div class="mock-row storeops-inline-123" >
            <div  class="storeops-inline-122">
                <span class="mock-label">{{ __('storeops::storeops.rules_ui.item_being_received') }}</span>
                <div  class="storeops-inline-121">
                    <i class="fa fa-box text-blue"></i> {{ __('storeops::rules.generic') }} {{ $category->name }}
                </div>
            </div>
            <div  class="storeops-inline-120">
                <span class="mock-label">{{ __('storeops::storeops.rules_ui.quantity') }}</span>
                <div class="mock-field storeops-inline-119" >1</div>
            </div>
        </div>

        <!-- Dynamic Simulated UI Requirements -->
        @foreach($simulatedUI as $code => $data)
            <div class="mock-row">
                <div class="mock-input-group">
                    <span class="mock-label">{{ $data['name'] }} <span class="text-danger">*</span></span>

                    @if($code === 'require_warranty')
                        <div class="mock-field">{{ __('storeops::rules.default_months', ['months' => $data['config']['warranty_months'] ?? 12]) }}</div>
                    @elseif($code === 'require_serial')
                        <div class="mock-field">{{ __('storeops::storeops.rules_ui.enter_unique_serial_number') }}</div>
                    @else
                        <div class="mock-field">{{ __('storeops::storeops.rules_ui.required_input') }}</div>
                    @endif
                </div>
            </div>
        @endforeach

        <hr  class="storeops-inline-118">

        <!-- Mock Post Button -->
        <div  class="storeops-inline-117">
            <button class="btn btn-success btn-lg disabled storeops-inline-116" ><i class="fa fa-lock"></i> {{ __('storeops::storeops.rules_ui.post_to_ledger') }}</button>
        </div>
    </div>

    <!-- RIGHT PANE: The "Why" Explanation -->
    <div class="sim-right">
        <div class="sim-header"><i class="fa fa-lightbulb-o"></i> {{ __('storeops::storeops.rules_ui.rule_explanation_the_why') }}</div>

        <div  class="storeops-inline-115">
            <p class="text-muted storeops-inline-114" >{{ __('storeops::storeops.rules_ui.standard_fields_always_shown_to_the_user') }}</p>
        </div>

        <!-- Dynamic Explanations -->
        @forelse($simulatedUI as $code => $data)
            <div class="why-box">
                <div class="why-title">{{ __('storeops::rules.required') }} {{ $data['name'] }}</div>
                <p class="why-desc">
                    {{ __('storeops::rules.mandated_by') }} <strong>{{ $data['source'] }}</strong>
                    <span class="label label-default storeops-inline-113" >{{ __('storeops::storeops.rules_ui.scope') }} {{ $data['layer'] }}</span>
                </p>
                @if(!empty($data['config']))
                    <p  class="storeops-inline-112"><i class="fa fa-sliders"></i> {{ __('storeops::storeops.rules_ui.configured_rules_applied') }}</p>
                @endif
            </div>
        @empty
            <div class="why-box storeops-inline-111" >
                <p class="why-desc text-muted">{{ __('storeops::storeops.rules_ui.no_additional_identification_or_receiving_rules_are_enforced_for') }}</p>
            </div>
        @endforelse

        <hr  class="storeops-inline-110">

        <!-- Backend Automations Explanation -->
        <h5  class="storeops-inline-109">{{ __('storeops::storeops.rules_ui.background_automations') }}</h5>
        @forelse($automations as $code => $data)
            <div  class="storeops-inline-108">
                <strong  class="storeops-inline-107">{{ $data['name'] }}</strong>
                <p  class="storeops-inline-106">{{ __('storeops::rules.automatic_post') }} <br>({{ __('storeops::rules.mandated_by') }}: {{ $data['source'] }})</p>
            </div>
        @empty
            <p class="text-muted storeops-inline-105" >{{ __('storeops::storeops.rules_ui.no_specific_automations_are_tied_to_this_item') }}</p>
        @endforelse

    </div>
</div>
