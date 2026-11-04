<?php

namespace Jason\FilamentChatAssistant\Services;

use Generator;
use Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

/**
 * AI 助手流式回复
 *
 * Agent 由宿主的 chat-assistant.agents 配置提供，包本身不绑定具体业务。
 */
class ChatAssistantService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages  当前会话消息
     * @param  object  $user  当前登录用户（agent 会话参与者）
     * @param  int|null  $tenantId  当前租户 ID；运营端为空
     * @param  string|null  $conversationId  agent 会话 ID
     * @param  string|null  $agentClass  当前 Agent 类名
     *
     * @return Generator<int, string, mixed, string|null>
     */
    public function stream(
        array $messages,
        object $user,
        ?int $tenantId = null,
        ?string $conversationId = null,
        ?string $agentClass = null,
    ): Generator {
        $prompt = (string) ($messages[array_key_last($messages)]['content'] ?? '');

        $history = [];

        foreach (array_slice($messages, 0, -1) as $message) {
            if (!isset($message['role'], $message['content'])) {
                continue;
            }

            if (!in_array($message['role'], ['user', 'assistant'], true)) {
                continue;
            }

            $history[] = new Message($message['role'], (string) $message['content']);
        }

        $agent = $this->resolveAgent($agentClass, $user, $tenantId, $history);

        if ($agent === null) {
            yield __('filament-chat-assistant::chat-assistant.agent_unavailable');

            return null;
        }

        if (method_exists($agent, 'continueOrStart')) {
            $agent = $agent->continueOrStart($conversationId, $user);
        }

        try {
            $stream = $agent->stream($prompt);

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    yield $event->delta;
                }
            }
        } catch (Throwable $exception) {
            report($exception);

            yield 'AI 服务暂时不可用，请稍后再试。';
        }

        return method_exists($agent, 'currentConversation')
            ? $agent->currentConversation()
            : null;
    }

    /**
     * @param  array<int, Message>  $history
     */
    protected function resolveAgent(
        ?string $agentClass,
        object $user,
        ?int $tenantId,
        array $history = [],
    ): ?ChatAssistantAgent {
        if (!is_string($agentClass) || !class_exists($agentClass)) {
            return null;
        }

        if (!is_subclass_of($agentClass, ChatAssistantAgent::class)) {
            return null;
        }

        $agent = app($agentClass, [
            'history' => $history,
            'user' => $user,
            'tenantId' => $tenantId,
        ]);

        return $agent instanceof ChatAssistantAgent ? $agent : null;
    }
}
