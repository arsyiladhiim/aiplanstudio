<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class AppSpecWebValidationTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    private function buildValidAppSpec(): string
    {
        return json_encode([
            'version' => '1',
            'generated_at' => '2026-08-18',
            'generated_from_stages' => ['analisa', 'phases_web', 'design_system'],
            'halaman' => [
                [
                    'key' => 'halaman_login',
                    'title' => 'Halaman Login',
                    'route' => '/login',
                    'phase_owner' => 'fase3_auth',
                    'description' => 'Form login dengan email + password',
                    'components_used' => ['comp_action_button'],
                    'design_signature' => 'glass panel center',
                ],
                [
                    'key' => 'halaman_dashboard',
                    'title' => 'Dashboard',
                    'route' => '/dashboard',
                    'phase_owner' => 'fase5_features',
                    'description' => 'Dashboard utama',
                    'components_used' => ['comp_metric_card'],
                    'design_signature' => 'asymmetric grid',
                ],
                [
                    'key' => 'halaman_list',
                    'title' => 'Daftar Item',
                    'route' => '/items',
                    'phase_owner' => 'fase5_features',
                    'description' => 'List items dengan filter',
                    'components_used' => ['comp_filter_bar'],
                    'design_signature' => 'sticky header',
                ],
            ],
            'navigation' => [
                'primary_menu' => [
                    ['key' => 'menu_dashboard', 'title' => 'Dashboard', 'icon' => 'home', 'route' => '/dashboard'],
                    ['key' => 'menu_list', 'title' => 'Items', 'icon' => 'list', 'route' => '/items'],
                ],
            ],
            'flows' => [
                [
                    'key' => 'flow_login',
                    'title' => 'First-time Login',
                    'steps' => [
                        ['order' => 1, 'from' => 'halaman_login', 'action' => 'submit form', 'to' => 'halaman_dashboard'],
                        ['order' => 2, 'from' => 'halaman_dashboard', 'action' => 'click create', 'to' => 'halaman_list'],
                    ],
                ],
            ],
            'components' => [
                [
                    'key' => 'comp_action_button',
                    'title' => 'Action Button',
                    'type' => 'primitive',
                    'used_in' => ['halaman_login'],
                    'props_signature' => 'interface Props { label: string; onClick: () => void; }',
                ],
                [
                    'key' => 'comp_metric_card',
                    'title' => 'Metric Card',
                    'type' => 'composite',
                    'used_in' => ['halaman_dashboard'],
                    'props_signature' => 'interface Props { title: string; value: number; }',
                ],
                [
                    'key' => 'comp_filter_bar',
                    'title' => 'Filter Bar',
                    'type' => 'composite',
                    'used_in' => ['halaman_list'],
                    'props_signature' => 'interface Props { filters: string[]; }',
                ],
            ],
        ], JSON_PRETTY_PRINT);
    }

    public function test_parses_valid_app_spec(): void
    {
        $result = $this->parser->parseAppSpecJson($this->buildValidAppSpec(), 'web');
        $this->assertNotNull($result['data'], 'Valid spec should parse: '.implode(', ', $result['errors']));
        $this->assertEmpty($result['errors']);
    }

    public function test_rejects_invalid_json(): void
    {
        $result = $this->parser->parseAppSpecJson('not json', 'web');
        $this->assertNull($result['data']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_missing_required_keys(): void
    {
        $result = $this->parser->parseAppSpecJson('{"halaman": [], "navigation": {}}', 'web');
        $this->assertNull($result['data']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_rejects_under_minimum_halaman(): void
    {
        $data = json_decode($this->buildValidAppSpec(), true);
        $data['halaman'] = array_slice($data['halaman'], 0, 2);
        $result = $this->parser->parseAppSpecJson(json_encode($data), 'web');
        $this->assertNull($result['data']);
        $this->assertStringContainsString("minimal 3 entry", implode(' ', $result['errors']));
    }

    public function test_rejects_invalid_component_used_in(): void
    {
        $data = json_decode($this->buildValidAppSpec(), true);
        $data['halaman'][0]['components_used'] = ['comp_nonexistent'];
        $result = $this->parser->parseAppSpecJson(json_encode($data), 'web');
        $this->assertNull($result['data']);
        $this->assertStringContainsString("comp_nonexistent", implode(' ', $result['errors']));
    }

    public function test_rejects_route_not_starting_with_slash(): void
    {
        $data = json_decode($this->buildValidAppSpec(), true);
        $data['halaman'][0]['route'] = 'login';
        $result = $this->parser->parseAppSpecJson(json_encode($data), 'web');
        $this->assertNull($result['data']);
        $this->assertStringContainsString("dimulai dengan '/'", implode(' ', $result['errors']));
    }

    public function test_rejects_flow_step_referencing_unknown_halaman(): void
    {
        $data = json_decode($this->buildValidAppSpec(), true);
        $data['flows'][0]['steps'][0]['from'] = 'halaman_nonexistent';
        $result = $this->parser->parseAppSpecJson(json_encode($data), 'web');
        $this->assertNull($result['data']);
        $this->assertStringContainsString("tidak ada di halaman registry", implode(' ', $result['errors']));
    }

    public function test_parses_mobile_platform_with_screens_key(): void
    {
        $data = json_decode($this->buildValidAppSpec(), true);
        $data['screens'] = $data['halaman'];
        unset($data['halaman']);
        $data['widgets'] = $data['components'];
        unset($data['components']);
        $result = $this->parser->parseAppSpecJson(json_encode($data), 'mobile');
        $this->assertNotNull($result['data'], 'Valid mobile spec: '.implode(', ', $result['errors']));
    }
}
