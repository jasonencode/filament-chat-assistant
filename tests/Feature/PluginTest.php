<?php

namespace Jason\FilamentChatAssistant\Tests\Feature;

use Jason\FilamentChatAssistant\FilamentChatAssistantPlugin;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestablePlugin;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestAgent;
use Jason\FilamentChatAssistant\Tests\TestCase;

class PluginTest extends TestCase
{
    public function test_it_has_a_stable_id(): void
    {
        $this->assertSame('filament-chat-assistant', FilamentChatAssistantPlugin::make()->getId());
    }

    public function test_it_does_not_render_for_guests(): void
    {
        $this->app['auth']->forgetGuards();

        $this->assertFalse((new TestablePlugin)->renders());
    }

    public function test_it_does_not_render_when_disabled(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.enabled' => false]);

        $this->assertFalse((new TestablePlugin)->renders());
    }

    public function test_it_does_not_render_when_no_agents_are_configured(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.agents' => []]);

        $this->assertFalse((new TestablePlugin)->renders());
    }

    public function test_it_renders_for_flat_agents_on_every_panel(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.agents' => ['test' => TestAgent::class]]);

        $this->assertTrue((new TestablePlugin)->renders());
    }

    public function test_it_renders_only_on_panels_with_a_group(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.agents' => [
            'admin' => ['test' => TestAgent::class],
            'tenant' => ['test' => TestAgent::class],
        ]]);

        $this->assertTrue((new TestablePlugin)->renders());

        config(['chat-assistant.agents' => [
            'tenant' => ['test' => TestAgent::class],
        ]]);

        $this->assertFalse((new TestablePlugin)->renders());
    }

    public function test_it_renders_on_unlisted_panels_when_a_wildcard_group_exists(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.agents' => [
            'tenant' => ['test' => TestAgent::class],
            '*' => ['test' => TestAgent::class],
        ]]);

        $this->assertTrue((new TestablePlugin)->renders());
    }

    public function test_it_persists_the_assistant_dom_across_spa_navigations(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.agents' => ['test' => TestAgent::class]]);

        $html = (new TestablePlugin)->html();

        $this->assertStringContainsString(
            'x-persist="filament-chat-assistant.panel-admin.tenant-platform"',
            $html,
        );
    }
}
