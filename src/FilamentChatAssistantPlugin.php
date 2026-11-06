<?php

namespace Jason\FilamentChatAssistant;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Jason\FilamentChatAssistant\Support\PanelAgents;

class FilamentChatAssistantPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filament-chat-assistant';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => $this->renderAssistant(),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    protected function shouldRender(): bool
    {
        if (!config('chat-assistant.enabled', true) || !filament()->auth()->check()) {
            return false;
        }

        $agents = config('chat-assistant.agents', []);

        if (!is_array($agents)) {
            return false;
        }

        // 只在实际配置了 Agent 的面板上渲染；分组写法下未列出的面板（且无 '*'）不渲染
        return PanelAgents::forPanel($agents, filament()->getCurrentPanel()?->getId()) !== [];
    }

    /**
     * 渲染助手组件。
     *
     * SPA 模式（wire:navigate）会整体替换 body，导致组件被销毁重建而闪动。
     * 用 @persist 包一层，让组件 DOM 在页面切换时原样保留。
     */
    protected function renderAssistant(): string
    {
        if (!$this->shouldRender()) {
            return '';
        }

        return Blade::render(
            "@persist('".$this->persistKey()."')"
            ."@livewire('filament-chat-assistant')"
            .'@endpersist',
        );
    }

    /**
     * SPA 导航时用于保留助手 DOM 的标识。
     *
     * 带上面板与租户，切换面板 / 租户时让旧组件被替换而不是串用。
     */
    protected function persistKey(): string
    {
        return 'filament-chat-assistant.panel-'.(filament()->getCurrentPanel()?->getId() ?? 'default')
            .'.tenant-'.(filament()->getTenant()?->getKey() ?? 'platform');
    }
}
