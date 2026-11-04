<?php

namespace Jason\FilamentChatAssistant\Tests\Feature;

use Jason\FilamentChatAssistant\Livewire\ChatAssistant;
use Jason\FilamentChatAssistant\Tests\TestCase;
use Livewire\Livewire;

class ConfigurationTest extends TestCase
{
    public function test_it_merges_the_default_configuration(): void
    {
        $this->assertTrue(config('chat-assistant.enabled'));
        $this->assertNull(config('chat-assistant.ui.title'));
        $this->assertSame(600, config('chat-assistant.ui.width'));
        $this->assertSame(800, config('chat-assistant.ui.height'));
        $this->assertTrue(config('chat-assistant.ui.fullscreen'));
        $this->assertTrue(config('chat-assistant.ui.new_conversation'));
        $this->assertSame([], config('chat-assistant.agents'));
        $this->assertSame([], config('chat-assistant.panels'));
    }

    public function test_it_registers_the_livewire_component(): void
    {
        $this->actingAsTestUser();

        Livewire::test(ChatAssistant::class)
            ->assertSet('open', false);
    }

    public function test_it_loads_the_chinese_and_english_translations(): void
    {
        $this->assertSame('AI-助手', __('filament-chat-assistant::chat-assistant.title', [], 'zh_CN'));
        $this->assertSame('AI Assistant', __('filament-chat-assistant::chat-assistant.title', [], 'en'));
    }
}
