<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class AppSpecMobileValidationTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    private function buildValidMobileSpec(): string
    {
        return json_encode([
            'version' => '1',
            'generated_at' => '2026-08-18',
            'generated_from_stages' => ['app_spec_web', 'design_system_mobile', 'phases_mobile'],
            'screens' => [
                [
                    'key' => 'screen_login',
                    'title' => 'Login',
                    'route' => '/login',
                    'dart_path' => 'lib/features/auth/presentation/login_screen.dart',
                    'phase_owner' => 'm_auth',
                    'description' => 'Login screen',
                    'widgets_used' => ['widget_primary_button'],
                    'design_signature' => 'tactile drag handle',
                ],
                [
                    'key' => 'screen_dashboard',
                    'title' => 'Dashboard',
                    'route' => '/dashboard',
                    'dart_path' => 'lib/features/dashboard/presentation/dashboard_screen.dart',
                    'phase_owner' => 'm_dashboard',
                    'description' => 'Dashboard',
                    'widgets_used' => ['widget_metric_card'],
                    'design_signature' => 'pull-to-refresh tactile',
                ],
                [
                    'key' => 'screen_list',
                    'title' => 'List',
                    'route' => '/list',
                    'dart_path' => 'lib/features/list/presentation/list_screen.dart',
                    'phase_owner' => 'm_crud',
                    'description' => 'List screen',
                    'widgets_used' => ['widget_search_bar'],
                    'design_signature' => 'sticky search header',
                ],
            ],
            'navigation' => [
                'primary_menu' => [
                    ['key' => 'nav_dashboard', 'title' => 'Dashboard', 'icon' => 'dashboard', 'route' => '/dashboard'],
                    ['key' => 'nav_list', 'title' => 'List', 'icon' => 'list', 'route' => '/list'],
                ],
            ],
            'flows' => [
                [
                    'key' => 'flow_login',
                    'title' => 'First launch',
                    'steps' => [
                        ['order' => 1, 'from' => 'screen_login', 'action' => 'submit', 'to' => 'screen_dashboard'],
                        ['order' => 2, 'from' => 'screen_dashboard', 'action' => 'tap', 'to' => 'screen_list'],
                    ],
                ],
            ],
            'widgets' => [
                [
                    'key' => 'widget_primary_button',
                    'title' => 'Primary Button',
                    'type' => 'primitive',
                    'used_in' => ['screen_login'],
                    'props_signature' => 'class Props { String label; VoidCallback onPressed; }',
                ],
                [
                    'key' => 'widget_metric_card',
                    'title' => 'Metric Card',
                    'type' => 'composite',
                    'used_in' => ['screen_dashboard'],
                    'props_signature' => 'class Props { String label; num value; }',
                ],
                [
                    'key' => 'widget_search_bar',
                    'title' => 'Search Bar',
                    'type' => 'composite',
                    'used_in' => ['screen_list'],
                    'props_signature' => 'class Props { ValueChanged<String> onChanged; }',
                ],
            ],
        ], JSON_PRETTY_PRINT);
    }

    public function test_parses_valid_mobile_spec(): void
    {
        $result = $this->parser->parseAppSpecJson($this->buildValidMobileSpec(), 'mobile');
        $this->assertNotNull($result['data'], implode(', ', $result['errors']));
        $this->assertEmpty($result['errors']);
    }

    public function test_rejects_web_spec_as_mobile(): void
    {
        $webSpec = json_encode([
            'version' => '1',
            'halaman' => [['key' => 'halaman_x', 'title' => 'X', 'route' => '/x']],
            'navigation' => ['primary_menu' => []],
            'flows' => [],
            'components' => [],
        ]);
        $result = $this->parser->parseAppSpecJson($webSpec, 'mobile');
        $this->assertNull($result['data']);
        $this->assertStringContainsString("'screens'", implode(' ', $result['errors']));
    }
}
