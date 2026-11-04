<?php

namespace Jason\FilamentChatAssistant;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

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

        $panels = config('chat-assistant.panels', []);

        return blank($panels) || in_array(filament()->getCurrentPanel()?->getId(), $panels, true);
    }
}
