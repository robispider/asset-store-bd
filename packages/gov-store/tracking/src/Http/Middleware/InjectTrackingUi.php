<?php

namespace GovStore\Tracking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use GovStore\Tracking\Models\TrackingReference;

class InjectTrackingUi
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful() && str_contains($response->headers->get('Content-Type') ?? '', 'text/html')) {
            $content = $response->getContent();

            // Intercept and inject Asset Creation Form Fields dynamically
            if ($request->is('hardware/create') || $request->is('hardware/*/edit')) {
                $content = $this->injectAssetFormFields($content, $request);
            }

            $response->setContent($content);
        }

        return $response;
    }

    protected function injectAssetFormFields(string $content, Request $request): string
    {
        $targetMarker = 'class="box-footer text-right"';
        if (!str_contains($content, $targetMarker)) {
            return $content;
        }

        $references = TrackingReference::with('trackingType')->where('status', 'ACTIVE')->get();

        $preSelectedId = '';
        if ($request->is('hardware/*/edit')) {
            $assetId = $request->route('hardware');
            $existingAssoc = \GovStore\Tracking\Models\TrackingAssociation::where('associatable_type', \App\Models\Asset::class)
                ->where('associatable_id', $assetId)
                ->where('status', 'ACTIVE')
                ->first();
            if ($existingAssoc) {
                $preSelectedId = $existingAssoc->tracking_reference_id;
            }
        }

        $optionsHtml = '<option value="">-- No Tracking Reference Associated --</option>';
        foreach ($references as $ref) {
            $selected = ($preSelectedId == $ref->id) ? 'selected' : '';
            $optionsHtml .= "<option value='{$ref->id}' {$selected}>{$ref->reference_code} - {$ref->title}</option>";
        }

        $formInjectionHtml = '
        <div class="row" style="margin-top: 15px; border-top: 1px solid #f4f4f4; padding-top: 15px;">
            <div class="col-md-10 col-md-offset-1">
                <div class="form-group">
                    <label class="col-md-3 control-label">Operational Reference</label>
                    <div class="col-md-7">
                        <select name="tracking_reference_id" id="tracking_reference_id" class="form-control" onchange="toggleOverrideReason()">
                            ' . $optionsHtml . '
                        </select>
                        <p class="help-block">Assign this asset lifecycle context to a tracking program reference.</p>
                    </div>
                </div>
                
                <div class="form-group" id="tracking_override_group" style="display: none;">
                    <label class="col-md-3 control-label">Override Justification</label>
                    <div class="col-md-7">
                        <textarea name="tracking_override_reason" class="form-control" placeholder="Provide a justification to bypass planning limits..."></textarea>
                        <p class="help-block text-yellow"><i class="fa fa-warning"></i> Mandatory if target allocations are exceeded on validation override rules.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            function toggleOverrideReason() {
                const selectVal = document.getElementById("tracking_reference_id").value;
                const overrideGroup = document.getElementById("tracking_override_group");
                overrideGroup.style.display = selectVal ? "block" : "none";
            }
            document.addEventListener("DOMContentLoaded", toggleOverrideReason);
        </script>
        ';

        $pos = strpos($content, '<div', strrpos($content, $targetMarker) - 300);
        if ($pos !== false) {
            return substr_replace($content, $formInjectionHtml, $pos, 0);
        }

        return $content;
    }
}