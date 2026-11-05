<?php

namespace Jason\FilamentChatAssistant\Tests\Unit;

use Illuminate\Support\Str;
use Jason\FilamentChatAssistant\Support\RememberedConversation;
use Jason\FilamentChatAssistant\Tests\Fixtures\RememberedAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestAgent;
use Jason\FilamentChatAssistant\Tests\TestCase;
use Laravel\Ai\Models\ConversationMessage;

class RememberedConversationTest extends TestCase
{
    public function test_it_detects_agents_that_remember_conversations(): void
    {
        $this->assertTrue(RememberedConversation::supports(RememberedAgent::class));
        $this->assertTrue(RememberedConversation::supports(TestAgent::class));
        $this->assertFalse(RememberedConversation::supports(\stdClass::class));
        $this->assertFalse(RememberedConversation::supports(null));
    }

    public function test_it_loads_user_and_completed_assistant_messages_only(): void
    {
        $this->store('c1', 'user', '你好');
        $this->store('c1', 'assistant', '回答');
        $this->store('c1', 'assistant', '失败的回复', 'failed');

        $messages = RememberedConversation::messages('c1');

        $this->assertSame(['user', 'assistant'], array_column($messages, 'role'));
        $this->assertSame('回答', $messages[1]['content']);
        $this->assertNotEmpty($messages[1]['id']);
    }

    public function test_it_returns_nothing_without_a_conversation(): void
    {
        $this->assertSame([], RememberedConversation::messages(null));
    }

    public function test_it_deletes_a_single_message(): void
    {
        $id = $this->store('c1', 'user', '你好');
        $this->store('c1', 'assistant', '回答');

        RememberedConversation::delete('c1', $id);

        $this->assertSame(['assistant'], array_column(RememberedConversation::messages('c1'), 'role'));
    }

    public function test_it_deletes_the_latest_assistant_message(): void
    {
        $this->store('c1', 'user', '你好');
        $this->store('c1', 'assistant', '回答一');
        $this->store('c1', 'user', '再问');
        $this->store('c1', 'assistant', '回答二');

        RememberedConversation::deleteLatestAssistant('c1');

        $this->assertSame(
            ['user', 'assistant', 'user'],
            array_column(RememberedConversation::messages('c1'), 'role'),
        );
    }

    /**
     * 写入一条会话消息
     */
    private function store(string $conversationId, string $role, string $content, string $status = 'completed'): string
    {
        $id = (string) Str::uuid7();

        ConversationMessage::query()->insert([
            'id' => $id,
            'conversation_id' => $conversationId,
            'participant_type' => null,
            'participant_id' => null,
            'agent' => RememberedAgent::class,
            'role' => $role,
            'content' => $content,
            'attachments' => '[]',
            'steps' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
