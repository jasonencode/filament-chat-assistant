<?php

namespace Jason\FilamentChatAssistant\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Jason\FilamentChatAssistant\Support\RememberedConversation;
use Jason\FilamentChatAssistant\Tests\Fixtures\RememberedAgent;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestAgent;
use Jason\FilamentChatAssistant\Tests\TestCase;
use Laravel\Ai\Models\ConversationMessage;

class RememberedConversationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('agent_conversations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('agent_conversation_messages', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content')->nullable();
            $table->text('attachments')->nullable();
            $table->longText('steps')->nullable();
            $table->text('usage')->nullable();
            $table->text('meta')->nullable();
            $table->string('status', 25);
            $table->timestamps();
        });
    }

    public function test_it_detects_agents_that_remember_conversations(): void
    {
        $this->assertTrue(RememberedConversation::supports(RememberedAgent::class));
        $this->assertFalse(RememberedConversation::supports(TestAgent::class));
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
