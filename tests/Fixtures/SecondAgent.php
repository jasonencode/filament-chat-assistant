<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

class SecondAgent extends TestAgent
{
    public function name(): string
    {
        return '第二助手';
    }

    public function avatar(): ?string
    {
        return asset('images/second.png');
    }
}
