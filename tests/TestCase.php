<?php

namespace Jason\FilamentChatAssistant\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Jason\FilamentChatAssistant\FilamentChatAssistantServiceProvider;
use Jason\FilamentChatAssistant\Tests\Fixtures\TestPanelProvider;
use Jason\FilamentChatAssistant\Tests\Fixtures\User;
use Laravel\Ai\AiServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('zh_CN');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            AiServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            FilamentChatAssistantServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
    }

    protected function makeUser(int $id = 1, string $name = '测试管理员'): User
    {
        return new User([
            'id' => $id,
            'name' => $name,
            'email' => "user{$id}@example.com",
        ]);
    }

    protected function actingAsTestUser(int $id = 1, string $name = '测试管理员'): User
    {
        $user = $this->makeUser($id, $name);

        $user->exists = true;

        $this->actingAs($user);

        return $user;
    }
}
