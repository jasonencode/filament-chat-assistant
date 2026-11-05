<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

use Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Promptable;

class RememberedAgent implements ChatAssistantAgent
{
    use Promptable;
    use RemembersConversations;

    public function __construct(
        public ?int $tenantId = null,
        public ?object $user = null,
    ) {
        //
    }

    public function name(): string
    {
        return '记忆助手';
    }

    public function avatar(): ?string
    {
        return null;
    }

    public function instructions(): string
    {
        return '你是记忆助手。';
    }
}
