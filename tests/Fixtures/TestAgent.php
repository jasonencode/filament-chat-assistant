<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

use Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Promptable;

class TestAgent implements ChatAssistantAgent
{
    use Promptable;
    use RemembersConversations;

    /** @var array<int, static> */
    public static array $instances = [];

    public function __construct(
        public ?int $tenantId = null,
        public ?object $user = null,
    ) {
        static::$instances[] = $this;
    }

    public static function reset(): void
    {
        static::$instances = [];
    }

    public function name(): string
    {
        return '测试助手';
    }

    public function avatar(): ?string
    {
        return null;
    }

    public function instructions(): string
    {
        return '你是测试助手。';
    }
}
