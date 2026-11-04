<?php

namespace Jason\FilamentChatAssistant\Tests\Fixtures;

class BrokenAgent extends TestAgent
{
    public function __construct(
        iterable $history = [],
        ?int $tenantId = null,
        ?object $user = null,
    ) {
        throw new \RuntimeException('Unable to construct the agent.');
    }
}
