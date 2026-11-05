<?php

namespace Jason\FilamentChatAssistant\Tests\Unit;

use Jason\FilamentChatAssistant\Services\ChatAssistantService;
use Jason\FilamentChatAssistant\Tests\Fixtures\ConversationAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\FailingAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestAgent;
use Jason\FilamentChatAssistant\Tests\TestCase;

class ChatAssistantServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestAgent::reset();
        ConversationAgent::reset();
    }

    public function test_it_yields_a_notice_when_no_agent_is_configured(): void
    {
        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
        );

        $this->assertSame(
            [__('filament-chat-assistant::chat-assistant.agent_unavailable')],
            iterator_to_array($stream),
        );
        $this->assertNull($stream->getReturn());
    }

    public function test_it_yields_a_notice_for_invalid_agent_classes(): void
    {
        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: \stdClass::class,
        );

        $this->assertSame(
            [__('filament-chat-assistant::chat-assistant.agent_unavailable')],
            iterator_to_array($stream),
        );
    }

    public function test_it_streams_text_deltas_from_the_agent(): void
    {
        TestAgent::fake(['回复内容']);

        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: TestAgent::class,
        );

        $this->assertSame('回复内容', implode('', iterator_to_array($stream)));
        $this->assertNull($stream->getReturn());
    }

    public function test_it_passes_previous_messages_as_history(): void
    {
        TestAgent::fake(['好的']);

        $user = $this->makeUser();

        $stream = $this->service()->stream(
            messages: [
                ['role' => 'user', 'content' => '第一问'],
                ['role' => 'assistant', 'content' => '第一答'],
                ['role' => 'system', 'content' => '忽略我'],
                ['content' => '缺少角色'],
                ['role' => 'user', 'content' => '第二问'],
            ],
            user: $user,
            tenantId: 9,
            agentClass: TestAgent::class,
        );

        iterator_to_array($stream);

        $agent = TestAgent::$instances[array_key_last(TestAgent::$instances)];

        $this->assertCount(2, $agent->history);
        $this->assertSame('user', $agent->history[0]->role->value);
        $this->assertSame('第一问', $agent->history[0]->content);
        $this->assertSame('assistant', $agent->history[1]->role->value);
        $this->assertSame('第一答', $agent->history[1]->content);
        $this->assertSame(9, $agent->tenantId);
        $this->assertSame($user, $agent->user);
    }

    public function test_it_continues_a_conversation_and_returns_its_id(): void
    {
        ConversationAgent::fake(['继续对话']);

        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            conversationId: 'conversation-1',
            agentClass: ConversationAgent::class,
        );

        $this->assertSame('继续对话', implode('', iterator_to_array($stream)));
        $this->assertSame('conversation-1', ConversationAgent::$continuedWith);
        $this->assertSame('conversation-2', $stream->getReturn());
    }

    public function test_it_yields_a_fallback_message_when_the_agent_fails(): void
    {
        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: FailingAgent::class,
        );

        $this->assertSame(
            ['AI 服务暂时不可用，请稍后再试。'],
            iterator_to_array($stream),
        );
    }

    public function test_it_hides_the_exception_detail_when_debug_is_disabled(): void
    {
        config(['chat-assistant.debug' => false]);

        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: FailingAgent::class,
        );

        $message = implode('', iterator_to_array($stream));

        $this->assertSame('AI 服务暂时不可用，请稍后再试。', $message);
        $this->assertStringNotContainsString('RuntimeException', $message);
        $this->assertStringNotContainsString('AI provider is unavailable.', $message);
    }

    public function test_it_appends_the_exception_detail_when_debug_is_enabled(): void
    {
        config(['chat-assistant.debug' => true]);

        $stream = $this->service()->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: FailingAgent::class,
        );

        $message = implode('', iterator_to_array($stream));

        $this->assertStringContainsString('AI 服务暂时不可用，请稍后再试。', $message);
        $this->assertStringContainsString('RuntimeException: AI provider is unavailable.', $message);
    }

    public function test_it_exposes_the_last_exception_after_a_failure(): void
    {
        $service = $this->service();

        iterator_to_array($service->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: FailingAgent::class,
        ));

        $this->assertInstanceOf(\RuntimeException::class, $service->lastException());
    }

    public function test_it_clears_the_last_exception_on_success(): void
    {
        $service = $this->service();

        iterator_to_array($service->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: FailingAgent::class,
        ));

        $this->assertNotNull($service->lastException());

        TestAgent::fake(['好的']);

        iterator_to_array($service->stream(
            messages: [['role' => 'user', 'content' => '你好']],
            user: $this->makeUser(),
            agentClass: TestAgent::class,
        ));

        $this->assertNull($service->lastException());
    }

    protected function service(): ChatAssistantService
    {
        return $this->app->make(ChatAssistantService::class);
    }
}
