<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

use Jason\FilamentChatAssistant\FilamentChatAssistantPlugin;

class TestablePlugin extends FilamentChatAssistantPlugin
{
    public function renders(): bool
    {
        return $this->shouldRender();
    }

    public function html(): string
    {
        return $this->renderAssistant();
    }
}
