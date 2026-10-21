<?php

namespace Tests\Feature\GovStore\Concerns;

use GovStore\GeoView\Support\JsonSchema;
use Illuminate\Support\Facades\Route;

/**
 * Every geo data source's test uses this trait (geo-view plan §11.1). The test supplies the source key, valid
 * params and three viewers; the trait checks the access rules and the payload contract.
 */
trait GeoDataSourceContract
{
    abstract protected function geoSourceKey(): string;

    /** Params a permitted viewer may send. */
    abstract protected function geoValidParams(): array;

    /** Signs in a viewer who may see the source's data. */
    abstract protected function geoActAsPermitted(): void;

    /** Signs in a viewer without the source's base ability (403). */
    abstract protected function geoActWithoutAbility(): void;

    /** Signs in a viewer with the ability but without access to the object (404). */
    abstract protected function geoActWithoutObjectAccess(): void;

    /** An area entirely outside the permitted viewer's scope, or null when the scope is every area. */
    abstract protected function geoAreaOutsideScope(): ?int;

    /** A ratio layer key and its offices' [num, den] sums at the root, or null when the source has none. */
    protected function geoRatioLayer(): ?string
    {
        return null;
    }

    /** Name of the object that must never appear in a 404 body (e.g. the programme's title). */
    protected function geoHiddenObjectName(): ?string
    {
        return null;
    }

    public function test_contract_routes_require_sign_in_and_throttle(): void
    {
        foreach (['gov.geo-view.data', 'gov.geo-view.details'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('throttle:geo-view', $middleware);
        }
    }

    public function test_contract_missing_ability_is_403(): void
    {
        $this->geoActWithoutAbility();
        $this->assertSame(403, $this->api('data', $this->geoSourceKey(), $this->geoValidParams() + ['area' => 'auto'])->getStatusCode());
    }

    public function test_contract_object_without_access_is_404_without_naming_it(): void
    {
        $this->geoActWithoutObjectAccess();
        $response = $this->api('data', $this->geoSourceKey(), $this->geoValidParams() + ['area' => 'auto']);
        $this->assertSame(404, $response->getStatusCode());
        if ($name = $this->geoHiddenObjectName()) {
            $this->assertStringNotContainsString($name, (string) $response->getContent());
        }
        $this->assertSame(404, $this->api('details', $this->geoSourceKey(), $this->geoValidParams() + ['area' => 1])->getStatusCode());
    }

    public function test_contract_tampered_params_are_422(): void
    {
        $this->geoActAsPermitted();
        $this->assertSame(422, $this->api('data', $this->geoSourceKey(), $this->geoValidParams() + ['area' => '1 or 1=1'])->getStatusCode());
        $this->assertSame(422, $this->api('data', $this->geoSourceKey(), $this->geoValidParams() + ['area' => 'auto', 'filters' => ['code' => '999999']])->getStatusCode());
        $this->assertSame(422, $this->api('data', $this->geoSourceKey(), ['area' => 'auto'] + array_map(fn () => 'x y z', $this->geoValidParams()))->getStatusCode());
    }

    public function test_contract_area_outside_scope_has_no_value_or_count(): void
    {
        $outside = $this->geoAreaOutsideScope();
        if ($outside === null) {
            $this->assertTrue(true);

            return;
        }
        $this->geoActAsPermitted();
        $payload = $this->payload($this->geoSourceKey(), $this->geoValidParams() + ['area' => $outside]);
        foreach ($payload['areas'] as $area) {
            $this->assertSame('no_access', $area['state']);
            $this->assertArrayNotHasKey('value', $area);
            $this->assertArrayNotHasKey('offices', $area);
        }
        $this->assertSame(0, $payload['here']['offices']);
        $this->assertSame([], $this->geoBody($this->api('details', $this->geoSourceKey(), $this->geoValidParams() + ['area' => $outside]))['offices']);
    }

    public function test_contract_payload_validates_and_names_follow_locale(): void
    {
        $this->geoActAsPermitted();
        $schema = JsonSchema::contract('gs-geo-data.schema.json');
        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $payload = $this->payload($this->geoSourceKey(), $this->geoValidParams() + ['area' => 'auto']);
            $this->assertSame([], JsonSchema::validate($schema, $payload), $locale);
            $root = $payload['area']['trail'][0]['name'];
            $locale === 'bn-BD' ? $this->assertMatchesRegularExpression('/\p{Bengali}/u', $root) : $this->assertDoesNotMatchRegularExpression('/\p{Bengali}/u', $root);
        }
    }

    public function test_contract_ratio_rolls_up_as_sum_of_numerators_over_sum_of_denominators(): void
    {
        $layer = $this->geoRatioLayer();
        if ($layer === null) {
            $this->assertTrue(true);

            return;
        }
        $this->geoActAsPermitted();
        $payload = $this->payload($this->geoSourceKey(), $this->geoValidParams() + ['area' => 'auto', 'layer' => $layer]);
        $num = 0;
        $den = 0;
        foreach ($payload['areas'] as $area) {
            if (isset($area['den'])) {
                $num += $area['num'];
                $den += $area['den'];
                $this->assertEqualsWithDelta($area['den'] > 0 ? $area['num'] / $area['den'] : null, $area['value'], 1e-6);
            }
        }
        if ($den > 0) {
            $this->assertEqualsWithDelta($num / $den, $payload['stats']['total'], 1e-6);
        }
    }

    private function geoBody($response): array
    {
        return json_decode((string) $response->getContent(), true);
    }
}
