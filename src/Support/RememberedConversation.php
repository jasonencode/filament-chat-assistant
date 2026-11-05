<?php

namespace Jason\FilamentChatAssistant\Support;

use Laravel\Ai\Concerns\RemembersConversations as RemembersConversationsTrait;
use Laravel\Ai\Contracts\RemembersConversations;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Models\ConversationMessage;

/**
 * 复用 Laravel AI 的会话表（agent_conversations / agent_conversation_messages）
 *
 * Agent 使用 RemembersConversations 时，消息由 AI SDK 自动落库；这里负责把这些
 * 落库记录读成对话框需要的数据结构，并提供撤回/重新生成所需的删除操作。
 */
final class RememberedConversation
{
    /**
     * Agent 是否启用了会话记忆（trait 或接口任一即可）
     */
    public static function supports(?string $agentClass): bool
    {
        if ($agentClass === null || !class_exists($agentClass)) {
            return false;
        }

        return is_subclass_of($agentClass, RemembersConversations::class)
            || in_array(RemembersConversationsTrait::class, class_uses_recursive($agentClass), true);
    }

    /**
     * 读取会话消息（隐藏失败/暂停的助手回合）
     *
     * @return array<int, array{id: string, role: string, content: string, created_at: string|null}>
     */
    public static function messages(?string $conversationId): array
    {
        if ($conversationId === null) {
            return [];
        }

        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->filter(fn (ConversationMessage $message): bool => $message->role !== 'assistant'
                || $message->status === MessageStatus::Completed)
            ->map(fn (ConversationMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => (string) $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * 删除单条消息
     */
    public static function delete(?string $conversationId, ?string $messageId): void
    {
        if ($conversationId === null || $messageId === null) {
            return;
        }

        ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereKey($messageId)
            ->delete();
    }

    /**
     * 删除最后一条助手回复，用于重新生成
     */
    public static function deleteLatestAssistant(?string $conversationId): void
    {
        if ($conversationId === null) {
            return;
        }

        ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderByDesc('id')
            ->first()
            ?->delete();
    }
}
