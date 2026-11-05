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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

        $this->createConversationTables();
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

        // Agent 必须使用 RemembersConversations，测试里用一个内存 SQLite 承载会话表
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('ai.conversations.generate_title', false);
    }

    /**
     * 创建 laravel/ai 的会话表（与官方迁移保持一致）
     */
    protected function createConversationTables(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('agent_conversation_messages', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('conversation_id', 36)->index();
            $table->string('participant_type')->nullable();
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->string('agent');
            $table->string('role', 25);
            $table->text('content')->nullable();
            $table->text('attachments')->nullable();
            $table->longText('steps')->nullable();
            $table->text('usage')->nullable();
            $table->text('meta')->nullable();
            $table->string('status', 25);
            $table->timestamps();
        });
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
