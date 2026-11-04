<?php

namespace Jason\FilamentChatAssistant;

use Jason\FilamentChatAssistant\Livewire\ChatAssistant;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentChatAssistantServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-chat-assistant';

    public static string $viewNamespace = 'filament-chat-assistant';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile('chat-assistant')
            ->hasViews(static::$viewNamespace)
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        Livewire::component('filament-chat-assistant', ChatAssistant::class);
    }
}
