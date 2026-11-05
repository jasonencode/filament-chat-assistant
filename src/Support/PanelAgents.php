<?php

namespace Jason\FilamentChatAssistant\Support;

/**
 * 按面板解析可用的 Agent 列表
 *
 * 同时供对话框组件与插件可见性判断使用，保证两处语义一致。
 */
final class PanelAgents
{
    /**
     * 解析指定面板可用的 Agent
     *
     * 平铺写法（值均为字符串）时所有面板共用；分组写法（存在数组值）时取面板 ID
     * 对应的分组，未列出的面板回落到 '*'，都没有则返回空数组。
     *
     * @param  array<array-key, mixed>  $agents
     * @return array<array-key, mixed>
     */
    public static function forPanel(array $agents, ?string $panelId): array
    {
        if ($agents === []) {
            return [];
        }

        if (!self::isGrouped($agents)) {
            return $agents;
        }

        $group = $panelId !== null ? ($agents[$panelId] ?? null) : null;

        $group ??= $agents['*'] ?? [];

        return is_array($group) ? $group : [];
    }

    /**
     * 判断配置是否为「按面板分组」写法
     *
     * @param  array<array-key, mixed>  $agents
     */
    public static function isGrouped(array $agents): bool
    {
        return array_filter($agents, static fn ($agent): bool => is_array($agent)) !== [];
    }
}
