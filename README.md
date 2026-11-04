# Filament Chat Assistant

给 Filament 面板挂一个右下角 AI 助手对话框的插件。插件只负责界面、会话隔离与流式输出；具体 Agent、工具和模型由宿主应用配置。

## 功能

- 右下角悬浮按钮与对话框，支持最大化 / 还原
- 拖拽上边、左边、左上角调整尺寸，尺寸自动保存到 `localStorage`，可一键还原默认尺寸
- 流式输出助手回复，带思考中动画；助手消息按 Markdown 渲染
- 可配置多个 Agent，标题栏下拉切换，每个 Agent 拥有独立会话
- 按「登录用户 + 租户 + Agent」隔离会话，切换租户或 Agent 不会串上下文
- 「开始新会话」、面板白名单、暗色模式、移动端自适应
- 内置简体中文与英文翻译

## 环境要求

- PHP 8.2+
- Filament 5.x
- Livewire 4.4+
- `laravel/ai` 1.3+

## 安装

```bash
composer require jason/filament-chat-assistant
```

发布配置文件：

```bash
php artisan vendor:publish --tag=filament-chat-assistant-config
```

需要定制视图或语言包时，再按需发布：

```bash
php artisan vendor:publish --tag=filament-chat-assistant-views
php artisan vendor:publish --tag=filament-chat-assistant-translations
```

## 注册插件

在面板 Provider 中注册：

```php
use Jason\FilamentChatAssistant\FilamentChatAssistantPlugin;

->plugin(FilamentChatAssistantPlugin::make())
```

插件会：

- 注册 Livewire 组件 `filament-chat-assistant`
- 在面板 `BODY_END` 渲染对话框，且仅在用户已登录时渲染
- 按「登录用户 + 租户」隔离会话
- 配置多个 Agent 时，标题栏名称可点击切换；每个 Agent 拥有独立会话记录
- 通过 `ChatAssistantService` 流式输出 Agent 回复

## 配置

`config/chat-assistant.php` 默认内容：

```php
return [
    'enabled' => env('FILAMENT_CHAT_ASSISTANT_ENABLED', true),

    // 名称、默认尺寸、全屏、新会话按钮
    'ui' => [
        // 为空时使用语言包中的 title；配置了 Agent 时优先显示 Agent 的 name()
        'title' => env('FILAMENT_CHAT_ASSISTANT_TITLE'),
        // 首次打开时的默认尺寸（像素），用户拖拽后的尺寸会保存在 localStorage
        'width' => env('FILAMENT_CHAT_ASSISTANT_WIDTH', 600),
        'height' => env('FILAMENT_CHAT_ASSISTANT_HEIGHT', 800),
        'fullscreen' => env('FILAMENT_CHAT_ASSISTANT_FULLSCREEN', true),
        'new_conversation' => env('FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION', true),
    ],

    // 可配置多个 Agent，对话框中可切换；数组键用于区分各自的会话记录
    // 每个 Agent 必须实现 ChatAssistantAgent，构造函数需接受 user / tenantId
    // 数组键可省略，省略时直接使用类名
    'agents' => [
        'order' => App\Ai\Agents\OrderSearch::class,
        'product' => App\Ai\Agents\ProductSearch::class,
    ],

    // 为空表示所有挂载插件的面板；可限制为 ['backend'] / ['tenant']
    'panels' => [],
];
```

发布后的配置文件会与包内默认值合并，只需保留要覆盖的键即可。

支持的环境变量：

| 变量 | 默认值 | 说明 |
| --- | --- | --- |
| `FILAMENT_CHAT_ASSISTANT_ENABLED` | `true` | 是否启用插件 |
| `FILAMENT_CHAT_ASSISTANT_TITLE` | 空 | 对话框名称，为空时使用语言包 |
| `FILAMENT_CHAT_ASSISTANT_WIDTH` | `600` | 默认宽度（像素） |
| `FILAMENT_CHAT_ASSISTANT_HEIGHT` | `800` | 默认高度（像素） |
| `FILAMENT_CHAT_ASSISTANT_FULLSCREEN` | `true` | 是否允许最大化 |
| `FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION` | `true` | 是否显示「开始新会话」按钮 |

## 编写 Agent

Agent 需实现 `Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent`，该接口继承自 Laravel AI 的 `Agent`，并额外要求提供名称和头像：

```php
use App\Models\System\Administrator;
use Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class OrderSearch implements ChatAssistantAgent, Conversational, HasTools
{
    use Promptable;

    public function __construct(
        public iterable $history = [],
        public ?int $tenantId = null,
        public ?Administrator $user = null,
    ) {}

    public function name(): string
    {
        return '订单查询助手';
    }

    public function avatar(): ?string
    {
        return asset('images/avatar.png'); // 返回 null 时使用名称首字占位
    }

    public function instructions(): string
    {
        return '你是订单查询助手……';
    }

    public function messages(): iterable
    {
        return is_array($this->history) ? $this->history : iterator_to_array($this->history);
    }

    public function tools(): iterable
    {
        return [
            // new SearchOrders($this->tenantId),
        ];
    }
}
```

构造函数可注入以下参数：

| 参数 | 说明 |
| --- | --- |
| `$user` | Filament 当前登录用户 |
| `$tenantId` | 当前面板租户 ID；平台面板为 `null` |
| `$history` | 历史消息（`Laravel\Ai\Messages\Message[]`），仅在回复请求时注入 |

> `$history` 必须提供默认值（如 `= []`）。渲染界面时容器只会注入 `user` 与 `tenantId`，缺少默认值会导致该 Agent 在列表中加载失败并被跳过。

`instructions()`、`tools()`、`messages()` 等能力由 `laravel/ai` 契约决定，按业务需要组合实现；修改指令或工具时不会影响插件本身。

### 持久化会话（可选）

如果希望对话记录由 `laravel/ai` 落库，可在 Agent 中使用 `RemembersConversations`：

```php
use Laravel\Ai\Concerns\RemembersConversations;

class OrderSearch implements ChatAssistantAgent, Conversational, HasTools
{
    use Promptable, RemembersConversations;
}
```

插件会在每次回复前调用 `continueOrStart($conversationId, $user)` 续接会话，并在回复结束后把 `currentConversation()` 返回的会话 ID 存入 session；点击「开始新会话」会清除该 ID。未使用该 trait 时，插件仅通过构造函数传入 `$history`，不负责持久化。

## 使用说明

- 会话数据保存在 session 中，不写入数据库；浏览器会话结束时失效
- session 键按「用户 + 租户 + Agent」区分，包含当前 Agent、消息、会话 ID 和展开状态
- 消息最多保留最近 50 条，单条输入上限 2000 字符
- Enter 发送，Shift + Enter 换行；输入法组合输入期间不会误发送
- 窗口尺寸与最大化状态保存在 `localStorage`（`tk-assistant-width`、`tk-assistant-height`、`tk-assistant-maximized`）
- 屏幕宽度 ≤ 640px 时对话框自动铺满，拖拽调整禁用

## 测试

包内使用 PHPUnit + Orchestra Testbench，测试不依赖宿主项目：

```bash
composer install
composer test
```

也可以直接运行：

```bash
vendor/bin/phpunit
```

覆盖范围：

- 配置合并、默认值与中英文翻译
- 插件渲染开关：登录状态、`enabled`、面板白名单
- 组件：Agent 解析与切换、无效 Agent 过滤、会话隔离、消息裁剪、输入校验、流式回复、会话恢复
- `ChatAssistantService`：历史消息传递、会话续接、异常兜底

> Testbench 开发依赖要求 PHP 8.3+。

## 许可证

MIT，详见 [LICENSE.md](LICENSE.md)。
