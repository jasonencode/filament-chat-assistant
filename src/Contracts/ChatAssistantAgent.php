<?php

namespace Jason\FilamentChatAssistant\Contracts;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\RemembersConversations;

/**
 * AI 助手 Agent 契约
 *
 * 接入插件的 Agent 必须使用 Laravel AI 的 RemembersConversations 能力
 * （通常通过 `use Laravel\Ai\Concerns\RemembersConversations` 引入 trait），
 * 会话历史由 AI SDK 的会话表负责持久化与读取。
 */
interface ChatAssistantAgent extends Agent, RemembersConversations
{
    /**
     * 助手的显示名称
     */
    public function name(): string;

    /**
     * 助手头像 URL，返回 null 时使用名称首字作为占位
     */
    public function avatar(): ?string;
}
