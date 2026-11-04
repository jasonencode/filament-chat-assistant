<?php

namespace Jason\FilamentChatAssistant\Contracts;

use Laravel\Ai\Contracts\Agent;

/**
 * AI 助手 Agent 契约
 *
 * 接入插件的 Agent 需实现本接口，并提供名称与头像。
 * Promptable、Conversational、会话记忆等能力由 Agent 自行组合实现。
 */
interface ChatAssistantAgent extends Agent
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
