<?php

namespace Jason\FilamentChatAssistant\Tests\Feature;

use Jason\FilamentChatAssistant\Livewire\ChatAssistant;
use Jason\FilamentChatAssistant\Tests\Fixtures\BrokenAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\SecondAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestAgent;
use Jason\FilamentChatAssistant\Tests\TestCase;
use Livewire\Livewire;

class ChatAssistantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTestUser();

        config(['chat-assistant.agents' => ['test' => TestAgent::class]]);
    }

    public function test_it_requires_an_authenticated_user(): void
    {
        $this->app['auth']->forgetGuards();

        $this->assertFalse(filament()->auth()->check());

        Livewire::test(ChatAssistant::class)
            ->assertForbidden();
    }

    public function test_it_renders_configured_window_options(): void
    {
        config([
            'chat-assistant.ui.width' => 420,
            'chat-assistant.ui.height' => 640,
            'chat-assistant.ui.fullscreen' => false,
            'chat-assistant.ui.new_conversation' => false,
        ]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('open', true)
            ->assertSee('this.width = 420;', false)
            ->assertSee('this.height = 640;', false)
            ->assertSee('maximized: false', false)
            ->assertDontSee('title="最大化"', false)
            ->assertDontSee('title="开始新会话"', false);
    }

    public function test_it_renders_fullscreen_and_new_conversation_by_default(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSee('title="最大化"', false)
            ->assertSee('title="开始新会话"', false)
            ->assertSee('tk-assistant__typing', false);
    }

    public function test_it_renders_single_agent_name_and_fallback_avatar(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', true)
            ->assertSee('测试助手')
            ->assertSee('你好，我是测试助手')
            ->assertSee('>测<', false)
            ->assertDontSee('aria-haspopup="listbox"', false);
    }

    public function test_agent_name_takes_priority_over_ui_title(): void
    {
        config(['chat-assistant.ui.title' => '自定义标题']);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSee('测试助手')
            ->assertDontSee('自定义标题');
    }

    public function test_missing_agents_show_a_notice_and_disable_the_composer(): void
    {
        config(['chat-assistant.agents' => []]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', false)
            ->assertSee('未配置有效的 AI Agent')
            ->assertSee('disabled', false);
    }

    public function test_invalid_agents_are_ignored(): void
    {
        config(['chat-assistant.agents' => [
            TestAgent::class,
            \stdClass::class,
            'App\\Ai\\Agents\\MissingAgent',
        ]]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', true)
            ->assertCount('agents', 1);
    }

    public function test_agents_that_fail_to_construct_are_ignored(): void
    {
        config(['chat-assistant.agents' => [
            'healthy' => TestAgent::class,
            'broken' => BrokenAgent::class,
        ]]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', true)
            ->assertCount('agents', 1)
            ->assertSee('测试助手');
    }

    public function test_non_array_agent_config_is_ignored(): void
    {
        config(['chat-assistant.agents' => TestAgent::class]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', false)
            ->assertCount('agents', 0);
    }

    public function test_multiple_agents_can_be_switched(): void
    {
        config(['chat-assistant.agents' => [
            'test' => TestAgent::class,
            'second' => SecondAgent::class,
        ]]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSee('测试助手')
            ->assertSee('第二助手')
            ->assertSee('aria-haspopup="listbox"', false)
            ->assertSet('agentName', '测试助手')
            ->call('selectAgent', 1)
            ->assertSet('agentName', '第二助手')
            ->assertSet('agentAvatar', asset('images/second.png'));
    }

    public function test_conversations_are_isolated_per_agent(): void
    {
        config(['chat-assistant.agents' => [
            'test' => TestAgent::class,
            'second' => SecondAgent::class,
        ]]);

        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '你好')
            ->call('sendMessage')
            ->assertCount('messages', 1);

        $component->call('selectAgent', 1)
            ->assertCount('messages', 0);

        $component->call('selectAgent', 0)
            ->assertCount('messages', 1)
            ->assertSet('messages.0.content', '你好');
    }

    public function test_it_sends_a_user_message(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '  你好  ')
            ->call('sendMessage')
            ->assertSet('question', '')
            ->assertCount('messages', 1)
            ->assertSet('messages.0.role', 'user')
            ->assertSet('messages.0.content', '你好')
            ->assertDispatched('chat-reply-requested');
    }

    public function test_it_validates_the_question_length(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', str_repeat('a', 2001))
            ->call('sendMessage')
            ->assertHasErrors('question')
            ->assertCount('messages', 0);
    }

    public function test_it_keeps_only_the_latest_fifty_messages(): void
    {
        $messages = [];

        for ($index = 1; $index <= 50; $index++) {
            $messages[] = ['role' => 'assistant', 'content' => "回复 {$index}"];
        }

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', $messages)
            ->set('question', '最新消息')
            ->call('sendMessage')
            ->assertCount('messages', 50)
            ->assertSet('messages.49.role', 'user')
            ->assertSet('messages.49.content', '最新消息');
    }

    public function test_it_streams_a_reply_from_the_agent(): void
    {
        TestAgent::fake(['这是测试回复']);

        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '你好')
            ->call('sendMessage');

        // Livewire 在测试环境会直接输出流式内容，这里用回调缓冲丢弃。
        ob_start(static fn (): string => '');

        try {
            $component->call('requestReply');
        } finally {
            ob_end_clean();
        }

        $component
            ->assertSet('replying', false)
            ->assertCount('messages', 2)
            ->assertSet('messages.1.role', 'assistant')
            ->assertSet('messages.1.content', '这是测试回复');
    }

    public function test_it_does_nothing_when_there_is_no_user_message_to_reply_to(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->call('requestReply')
            ->assertSet('replying', false)
            ->assertCount('messages', 0);
    }

    public function test_it_resets_the_conversation(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '测试')
            ->call('sendMessage')
            ->call('resetConversation')
            ->assertSet('messages', [])
            ->assertSet('question', '');
    }

    public function test_messages_are_restored_on_the_next_mount(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '持久化消息')
            ->call('sendMessage');

        Livewire::test(ChatAssistant::class)
            ->assertSet('open', true)
            ->assertCount('messages', 1)
            ->assertSet('messages.0.content', '持久化消息');
    }

    public function test_conversations_are_isolated_per_user(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '用户一的消息')
            ->call('sendMessage');

        $this->actingAsTestUser(2, '另一个用户');

        Livewire::test(ChatAssistant::class)
            ->assertCount('messages', 0);
    }
}
