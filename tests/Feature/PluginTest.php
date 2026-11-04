<?php

namespace Jason\FilamentChatAssistant\Tests\Feature;

use Jason\FilamentChatAssistant\FilamentChatAssistantPlugin;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestablePlugin;
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

    public function test_it_renders_for_authenticated_users_on_all_panels_by_default(): void
    {
        $this->actingAsTestUser();

        config([
            'chat-assistant.enabled' => true,
            'chat-assistant.panels' => [],
        ]);

        $this->assertTrue((new TestablePlugin)->renders());
    }

    public function test_it_renders_only_on_whitelisted_panels(): void
    {
        $this->actingAsTestUser();

        config(['chat-assistant.panels' => ['admin']]);

        $this->assertTrue((new TestablePlugin)->renders());

        config(['chat-assistant.panels' => ['tenant']]);

        $this->assertFalse((new TestablePlugin)->renders());
    }
}
