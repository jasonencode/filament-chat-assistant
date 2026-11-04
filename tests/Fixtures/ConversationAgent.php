<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

class ConversationAgent extends TestAgent
{
    public static ?string $continuedWith = null;

    public static function reset(): void
    {
        parent::reset();

        static::$continuedWith = null;
    }

    public function continueOrStart(?string $conversationId, object $as): static
    {
        static::$continuedWith = $conversationId;

        return $this;
    }

    public function currentConversation(): ?string
    {
        return 'conversation-2';
    }
}
