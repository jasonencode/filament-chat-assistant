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
            function (): string {
                if (!$this->shouldRender()) {
                    return '';
                }

                return Blade::render("@livewire('filament-chat-assistant')");
            },
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
}
