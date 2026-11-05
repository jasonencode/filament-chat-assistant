# Filament Chat Assistant

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![Tests](https://github.com/jasonencode/filament-chat-assistant/actions/workflows/tests.yml/badge.svg)](https://github.com/jasonencode/filament-chat-assistant/actions/workflows/tests.yml)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![Total Downloads](https://img.shields.io/packagist/dt/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![License](https://img.shields.io/packagist/l/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![Filament](https://img.shields.io/badge/filament-5.x-orange.svg?style=flat-square)](https://filamentphp.com)
[![Livewire](https://img.shields.io/badge/livewire-4.4%2B-pink.svg?style=flat-square)](https://livewire.laravel.com)

[English](README.md) | **简体中文**

给 Filament 面板挂一个右下角 AI 助手对话框的插件。插件只负责界面、会话隔离、流式输出与消息操作；具体 Agent、工具、模型以及是否落库由 `laravel/ai` 与宿主应用决定。

## 功能

- 右下角悬浮按钮与对话框，支持最大化 / 还原
- 拖拽上边、左边、左上角调整尺寸，尺寸保存在 `localStorage`，可一键还原默认尺寸
- 流式输出助手回复，Markdown 渲染；首个分片到达后自动隐藏「正在思考」，头像与名称保持可见
- 多 Agent，标题栏下拉切换；支持按面板配置不同 Agent
- 会话按「登录用户 + 租户 + Agent 配置键」隔离，切换租户 / Agent 不串上下文
- 消息操作：复制、编辑（用户消息回填到输入框）、撤回（二次确认）、重新生成（最后一条回复）
- 消息时间：今天显示 `H:i`，今年显示 `m-d H:i`，更早显示 `Y-m-d H:i`（Filament 时区）
- 输入区：清空按钮（在输入框内部）、Enter 发送 / Shift+Enter 换行、输入法组合输入保护
- 状态指示器：就绪为绿色、生成中为琥珀色呼吸、上次失败为红色
- 关闭态悬浮图标可配置为 png / jpg 图片或完整 URL
- 错误友好提示；异常详情仅在 debug 开启时显示，完整堆栈始终写入日志
- 会话可落库：Agent 使用 `RemembersConversations` 时复用 `agent_conversations` / `agent_conversation_messages`
- 暗色模式、移动端自适应、简体中文与英文翻译

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

如果打算使用「会话落库」，还需要按 `laravel/ai` 文档发布会话表迁移并执行：

```bash
php artisan vendor:publish   # 选择 laravel/ai 的 migrations
php artisan migrate
```

## 注册插件

在面板 Provider 中注册：

```php
use Jason\FilamentChatAssistant\FilamentChatAssistantPlugin;

->plugin(FilamentChatAssistantPlugin::make())
```

插件会：

- 注册 Livewire 组件 `filament-chat-assistant`
- 在面板 `BODY_END` 渲染对话框，仅在用户已登录时渲染
- 只在「当前面板解析出至少一个 Agent」时挂载（未配置 Agent 的面板不出现任何按钮）
- 通过 `ChatAssistantService` 流式输出 Agent 回复

## 配置

`config/chat-assistant.php` 完整示例：

```php
return [
    'enabled' => env('FILAMENT_CHAT_ASSISTANT_ENABLED', true),

    'ui' => [
        // 首次打开时的默认尺寸（像素），拖拽后的尺寸保存在 localStorage
        'width' => env('FILAMENT_CHAT_ASSISTANT_WIDTH', 600),
        'height' => env('FILAMENT_CHAT_ASSISTANT_HEIGHT', 800),
        'fullscreen' => env('FILAMENT_CHAT_ASSISTANT_FULLSCREEN', true),
        'new_conversation' => env('FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION', true),

        // 关闭态图标：public 下的相对路径（如 images/assistant.png）或完整 URL；留空使用内置图标
        'toggle_icon' => env('FILAMENT_CHAT_ASSISTANT_TOGGLE_ICON'),
    ],

    // 流式请求的 PHP 执行时间上限（秒），0 表示不限制
    'timeout' => (int) env('FILAMENT_CHAT_ASSISTANT_TIMEOUT', 300),

    // 是否在错误气泡中附带异常类名与 message；默认跟随 APP_DEBUG
    'debug' => (bool) env('FILAMENT_CHAT_ASSISTANT_DEBUG', env('APP_DEBUG', false)),

    // 平铺写法：所有面板共用
    'agents' => [
        'order' => App\Ai\Agents\OrderSearch::class,
        'report' => App\Ai\Agents\OrderReport::class,
    ],

    // 按面板分组：键为面板 ID，'*' 兜底未列出的面板
    // 'agents' => [
    //     'backend' => ['order' => App\Ai\Agents\OrderSearch::class],
    //     'tenant' => ['product' => App\Ai\Agents\ProductSearch::class],
    //     '*' => ['order' => App\Ai\Agents\OrderSearch::class],
    // ],
];
```

### Agent 配置

`agents` 支持两种写法：

- **平铺**：所有面板共用同一组 Agent
- **按面板分组**：键为面板 ID（`backend` / `tenant` 等），当前面板取对应的那组；未列出的面板使用 `'*'` 兜底，连 `'*'` 也没有则该面板不渲染对话框

数组键用于区分会话记录：**两个不同的键即使指向同一个 Agent 类，也会各自拥有独立会话**。

插件只在「当前面板解析出至少一个 Agent」时挂载，因此不再需要额外的面板白名单配置。

### 环境变量

| 变量 | 默认值 | 说明 |
| --- | --- | --- |
| `FILAMENT_CHAT_ASSISTANT_ENABLED` | `true` | 是否启用插件 |
| `FILAMENT_CHAT_ASSISTANT_WIDTH` | `600` | 默认宽度（像素） |
| `FILAMENT_CHAT_ASSISTANT_HEIGHT` | `800` | 默认高度（像素） |
| `FILAMENT_CHAT_ASSISTANT_FULLSCREEN` | `true` | 是否允许最大化 |
| `FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION` | `true` | 是否显示「开始新会话」按钮 |
| `FILAMENT_CHAT_ASSISTANT_TOGGLE_ICON` | 空 | 关闭态图标，相对路径或完整 URL |
| `FILAMENT_CHAT_ASSISTANT_TIMEOUT` | `300` | 流式请求执行时间上限（秒），0 为不限制 |
| `FILAMENT_CHAT_ASSISTANT_DEBUG` | 跟随 `APP_DEBUG` | 错误气泡是否显示异常详情 |

发布后的配置文件会与包内默认值合并，只需保留要覆盖的键即可。

### 错误提示

AI 调用失败时，对话框里始终只显示「AI 服务暂时不可用，请稍后再试。」；异常类名与 message 仅在 `chat-assistant.debug`（默认跟随 `APP_DEBUG`）开启时以引用块附在气泡里。生产环境保持 `APP_DEBUG=false` 即可，用户看不到任何异常细节；完整堆栈仍会通过 `report()` 写入日志。

流式请求开始前会调用 `set_time_limit(config('chat-assistant.timeout'))`，避免 PHP 默认 30 秒上限触发不可捕获的 `FatalError`。若服务器通过 `disable_functions` 禁用了 `set_time_limit`，仍需在 php.ini / FPM 中调整 `max_execution_time`。

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
| `$history` | 历史消息（`Laravel\Ai\Messages\Message[]`），仅在未启用会话落库时注入 |

> `$history` 必须提供默认值（如 `= []`）。渲染界面时容器只会注入 `user` 与 `tenantId`，缺少默认值会导致该 Agent 在列表中加载失败并被跳过。

`instructions()`、`tools()`、`messages()` 等能力由 `laravel/ai` 契约决定，按业务需要组合实现；修改指令或工具时不会影响插件本身。

### 会话落库（可选）

希望对话记录落库时，让 Agent 使用 `laravel/ai` 的 `RemembersConversations`（trait + 接口）：

```php
use Laravel\Ai\Concerns\RemembersConversations as RemembersConversationsTrait;
use Laravel\Ai\Contracts\RemembersConversations;

class OrderSearch implements ChatAssistantAgent, RemembersConversations, HasTools
{
    use Promptable;
    use RemembersConversationsTrait;

    public function __construct(
        public ?int $tenantId = null,
        public ?Administrator $user = null,
    ) {}
}
```

启用后：

- 消息由 AI SDK 自动写入 `agent_conversations` / `agent_conversation_messages`
- 插件在有会话 ID 时直接读这两张表渲染对话框；「撤回」「重新生成」会删除对应的数据库行，AI 后续上下文同步移除
- 「开始新会话」只是切换新的会话 ID，旧会话仍保留在数据库中
- **不要在 Agent 里再自定义 `messages()`**，否则会覆盖 trait 从数据库读取历史的实现
- 建议关闭自动生成标题（本仓库 `config/ai.php` 已设置 `ai.conversations.generate_title = false`），避免新会话额外请求一次模型

未使用该能力时，插件仍走 session + 构造函数传入的 `$history`，行为不变。

## 界面交互

### 消息操作

每条消息下方都有操作按钮：

| 操作 | 可用范围 | 说明 |
| --- | --- | --- |
| 复制 | 所有消息 | 复制原始文本（助手消息为 Markdown 源文本），成功后短暂显示「已复制」 |
| 编辑 | 用户消息 | 把内容回填到输入框并聚焦，发送后作为新消息追加 |
| 撤回 | 所有消息 | 二次确认后删除；落库模式下同时删除数据库行 |
| 重新生成 | 最后一条助手消息 | 删除该回复，用上一条用户消息重新请求 |

生成中（`replying`）会忽略编辑、撤回与重新生成。

### 时间与状态

- 消息时间跟随 Filament 时区，悬停显示完整 `Y-m-d H:i:s`
- 标题栏状态点：绿色=就绪，琥珀色呼吸=正在生成，红色=上次回复失败；状态只保存在当前组件，刷新页面后回到绿色

### 输入区

- 输入框内部右侧是「清空」与「发送」按钮
- Enter 发送，Shift + Enter 换行；输入法组合输入期间不会误发送
- 单条输入上限 2000 字符；消息最多保留最近 50 条

### 窗口

- 拖拽上边 / 左边 / 左上角调整宽高；`localStorage` 保存 `tk-assistant-width`、`tk-assistant-height`、`tk-assistant-maximized`
- 屏幕宽度 ≤ 640px 时自动铺满，拖拽调整禁用

## 数据与隔离

| Agent 类型 | 消息存储 | 会话 ID |
| --- | --- | --- |
| 普通 `Conversational` | session（`filament-chat-assistant.messages.*`） | session |
| `RemembersConversations` | 数据库（`agent_conversation_messages`） | session 中只存会话 ID |

- 隔离维度：登录用户 + 租户（平台面板为 `platform`）+ Agent 配置键
- 落库模式下 session 仍保留一份最多 50 条的 UI 缓存，用于切换 Agent / 尚未回复的消息
- session 键会随 Agent 配置键变化；修改配置键会开启一段新的本地会话

## 已知限制

- 落库模式下，用户消息在整轮回复完成时才由 SDK 写库，因此刷新后用户气泡的时间是完成时刻，不是发送时刻
- 「开始新会话」不会删除旧会话记录；目前没有会话列表 / 清理入口，需要时自行加命令或管理页
- 未启用 `RemembersConversations` 时消息只存在 session，浏览器会话结束即失效
- 纯工具调用、没有文本输出的回合在落库模式下可能出现空的助手气泡
- 包内只提供 `zh_CN` 与 `en` 翻译；`zh_TW` 等语言会回退到宿主应用的 fallback locale
- `agents` 分组判定规则是「存在数组值即视为分组」；平铺配置中不要放数组值

## 测试

包内使用 PHPUnit + Orchestra Testbench，测试不依赖宿主项目：

```bash
composer install
vendor/bin/phpunit
```

覆盖范围：

- 配置合并、默认值、中英文翻译
- 插件渲染开关：登录状态、`enabled`、按面板 / `*` 解析 Agent
- 组件：Agent 解析与切换、同类多实例隔离、无效 Agent 过滤、消息裁剪、输入校验、流式回复、复制 / 编辑 / 撤回 / 重新生成、清空输入、时间与状态渲染
- 会话落库：`RememberedConversation` 的读取与删除
- `ChatAssistantService`：历史消息传递、会话续接、异常兜底、异常详情开关

> Testbench 开发依赖要求 PHP 8.3+。

## 许可证

MIT，详见 [LICENSE.md](LICENSE.md)。
