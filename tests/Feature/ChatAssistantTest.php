<?php

namespace Jason\FilamentChatAssistant\Tests\Feature;

use Jason\FilamentChatAssistant\Livewire\ChatAssistant;
use Jason\FilamentChatAssistant\Tests\Fixtures\BrokenAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\FailingAgent;
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

    public function test_agents_can_be_configured_per_panel(): void
    {
        config(['chat-assistant.agents' => [
            'admin' => ['test' => TestAgent::class],
            'tenant' => ['second' => SecondAgent::class],
        ]]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', true)
            ->assertCount('agents', 1)
            ->assertSee('测试助手')
            ->assertDontSee('第二助手');
    }

    public function test_panels_without_a_group_fall_back_to_the_wildcard_group(): void
    {
        config(['chat-assistant.agents' => [
            'tenant' => ['second' => SecondAgent::class],
            '*' => ['test' => TestAgent::class],
        ]]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', true)
            ->assertCount('agents', 1)
            ->assertSee('测试助手')
            ->assertDontSee('第二助手');
    }

    public function test_panels_without_a_group_or_wildcard_have_no_agents(): void
    {
        config(['chat-assistant.agents' => [
            'tenant' => ['second' => SecondAgent::class],
        ]]);

        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSet('agentConfigured', false)
            ->assertCount('agents', 0)
            ->assertSee('未配置有效的 AI Agent');
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

    public function test_it_renders_a_configured_toggle_icon(): void
    {
        config(['chat-assistant.ui.toggle_icon' => 'images/assistant.png']);

        Livewire::test(ChatAssistant::class)
            ->assertSee('src="'.asset('images/assistant.png').'"', false)
            ->assertSee('class="tk-assistant__toggle-icon"', false)
            ->assertDontSee('M12 8V4H8', false);
    }

    public function test_it_renders_an_external_toggle_icon_url(): void
    {
        config(['chat-assistant.ui.toggle_icon' => 'https://cdn.example.com/assistant.jpg']);

        Livewire::test(ChatAssistant::class)
            ->assertSee('src="https://cdn.example.com/assistant.jpg"', false);
    }

    public function test_it_falls_back_to_the_built_in_toggle_icon(): void
    {
        Livewire::test(ChatAssistant::class)
            ->assertSee('M12 8V4H8', false)
            ->assertDontSee('class="tk-assistant__toggle-icon"', false);
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

    public function test_messages_carry_a_creation_time(): void
    {
        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '你好')
            ->call('sendMessage');

        $this->assertNotEmpty($component->get('messages')[0]['created_at']);

        TestAgent::fake(['好的']);

        ob_start(static fn (): string => '');

        try {
            $component->call('requestReply');
        } finally {
            ob_end_clean();
        }

        $messages = $component->get('messages');

        $this->assertCount(2, $messages);
        $this->assertNotEmpty($messages[1]['created_at']);
    }

    public function test_it_renders_the_message_time(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '你好', 'created_at' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => '你好呀', 'created_at' => now()->toIso8601String()],
            ])
            ->assertSee('<time class="tk-assistant__message-time"', false)
            ->assertSee('<time', false);
    }

    public function test_each_message_has_a_copy_button(): void
    {
        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '你好 "世界"', 'created_at' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => '你好呀', 'created_at' => now()->toIso8601String()],
            ]);

        $html = $component->html();

        $this->assertSame(2, substr_count($html, 'class="tk-assistant__copy"'));
        $this->assertStringContainsString('data-copy="你好 &quot;世界&quot;"', $html);
        $this->assertStringContainsString('data-copy-label="复制"', $html);
        $this->assertStringContainsString('data-copied-label="已复制"', $html);
    }

    public function test_a_user_message_can_be_loaded_into_the_input(): void
    {
        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '第一版内容', 'created_at' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => '答复', 'created_at' => now()->toIso8601String()],
            ])
            ->call('editMessage', 0)
            ->assertSet('question', '第一版内容')
            ->assertDispatched('chat-edit-message');

        // 助手消息不可编辑
        $component->call('editMessage', 1)->assertSet('question', '第一版内容');

        // 越界索引不报错
        $component->call('editMessage', 9)->assertSet('question', '第一版内容');
    }

    public function test_editing_is_ignored_while_replying(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [['role' => 'user', 'content' => '内容', 'created_at' => now()->toIso8601String()]])
            ->set('replying', true)
            ->call('editMessage', 0)
            ->assertSet('question', '')
            ->assertNotDispatched('chat-edit-message');
    }

    public function test_only_user_messages_have_an_edit_button(): void
    {
        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '问题'],
                ['role' => 'assistant', 'content' => '回答'],
            ]);

        $html = $component->html();

        $this->assertSame(1, substr_count($html, 'class="tk-assistant__edit"'));
        $this->assertStringContainsString('wire:click="editMessage(0)"', $html);
    }

    public function test_the_input_can_be_cleared(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '待清空')
            ->call('clearQuestion')
            ->assertSet('question', '')
            ->assertDispatched('chat-input-cleared');
    }

    public function test_clearing_the_input_resets_the_validation_error(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', str_repeat('a', 2001))
            ->call('sendMessage')
            ->assertHasErrors('question')
            ->call('clearQuestion')
            ->assertHasNoErrors('question');
    }

    public function test_the_composer_renders_a_clear_button(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->assertSee('class="tk-assistant__clear"', false)
            ->assertSee('wire:click="clearQuestion"', false);
    }

    public function test_a_message_can_be_retracted(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '问题'],
                ['role' => 'assistant', 'content' => '回答'],
            ])
            ->call('retractMessage', 1)
            ->assertCount('messages', 1)
            ->assertSet('messages.0.role', 'user');

        // 已写入 session，重新挂载仍然是 1 条
        Livewire::test(ChatAssistant::class)->assertCount('messages', 1);
    }

    public function test_retracting_is_ignored_while_replying(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [['role' => 'user', 'content' => '问题']])
            ->set('replying', true)
            ->call('retractMessage', 0)
            ->assertCount('messages', 1);
    }

    public function test_the_last_assistant_reply_can_be_regenerated(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '问题'],
                ['role' => 'assistant', 'content' => '回答'],
            ])
            ->call('regenerateReply')
            ->assertCount('messages', 1)
            ->assertSet('messages.0.role', 'user')
            ->assertDispatched('chat-reply-requested');
    }

    public function test_regenerate_does_nothing_when_the_last_message_is_not_an_assistant_reply(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [['role' => 'user', 'content' => '问题']])
            ->call('regenerateReply')
            ->assertCount('messages', 1)
            ->assertNotDispatched('chat-reply-requested');
    }

    public function test_only_the_last_assistant_reply_offers_regeneration(): void
    {
        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [
                ['role' => 'user', 'content' => '问题一'],
                ['role' => 'assistant', 'content' => '回答一'],
                ['role' => 'user', 'content' => '问题二'],
                ['role' => 'assistant', 'content' => '回答二'],
            ]);

        $html = $component->html();

        $this->assertSame(1, substr_count($html, 'class="tk-assistant__regenerate"'));
        $this->assertSame(4, substr_count($html, 'class="tk-assistant__retract"'));
    }

    public function test_messages_without_a_creation_time_render_without_a_time(): void
    {
        Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('messages', [['role' => 'user', 'content' => '旧消息']])
            ->assertSee('旧消息')
            ->assertDontSee('class="tk-assistant__message-time"', false);
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

    public function test_it_shows_agent_failures_inside_the_conversation(): void
    {
        config(['chat-assistant.agents' => ['failing' => FailingAgent::class]]);

        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '你好')
            ->call('sendMessage');

        ob_start(static fn (): string => '');

        try {
            $component->call('requestReply');
        } finally {
            ob_end_clean();
        }

        $component
            ->assertSet('replying', false)
            ->assertSet('hasError', true)
            ->assertCount('messages', 2)
            ->assertSet('messages.1.role', 'assistant')
            ->assertSet('messages.1.content', __('filament-chat-assistant::chat-assistant.error_service'))
            ->assertSee('class="tk-assistant__status tk-assistant__status--error"', false);
    }

    public function test_it_reports_a_successful_reply_in_the_status_indicator(): void
    {
        TestAgent::fake(['好的']);

        $component = Livewire::test(ChatAssistant::class)
            ->call('toggle')
            ->set('question', '你好')
            ->call('sendMessage');

        ob_start(static fn (): string => '');

        try {
            $component->call('requestReply');
        } finally {
            ob_end_clean();
        }

        $component
            ->assertSet('hasError', false)
            ->assertSee('class="tk-assistant__status"', false)
            ->assertDontSee('class="tk-assistant__status tk-assistant__status--error"', false);
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
