<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

use Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class TestAgent implements ChatAssistantAgent, Conversational
{
    use Promptable;

    /** @var array<int, static> */
    public static array $instances = [];

    /**
     * @param  iterable<Message>  $history
     */
    public function __construct(
        public iterable $history = [],
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

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return is_array($this->history) ? $this->history : iterator_to_array($this->history);
    }
}
