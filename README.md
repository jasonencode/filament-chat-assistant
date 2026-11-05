# Filament Chat Assistant

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![Tests](https://github.com/jasonencode/filament-chat-assistant/actions/workflows/tests.yml/badge.svg)](https://github.com/jasonencode/filament-chat-assistant/actions/workflows/tests.yml)
[![PHP Version Require](https://img.shields.io/packagist/php-v/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![Total Downloads](https://img.shields.io/packagist/dt/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![License](https://img.shields.io/packagist/l/jason/filament-chat-assistant.svg?style=flat-square)](https://packagist.org/packages/jason/filament-chat-assistant)
[![Filament](https://img.shields.io/badge/filament-5.x-orange.svg?style=flat-square)](https://filamentphp.com)
[![Livewire](https://img.shields.io/badge/livewire-4.4%2B-pink.svg?style=flat-square)](https://livewire.laravel.com)

**English** | [简体中文](README.zh_CN.md)

An AI chat assistant dialog plugin for Filament panels. The package owns the UI, conversation isolation, streaming output and message actions; the agents, tools, models and optional persistence come from `laravel/ai` and the host application.

## Features

- Floating button and dialog in the bottom-right corner, with maximize / restore
- Resize by dragging the top, left or top-left edge; the size is saved in `localStorage` and can be reset
- Streaming replies rendered as Markdown; the thinking animation disappears after the first chunk while the avatar and name stay visible
- Multiple agents switchable from the header, including per-panel agent sets
- Conversations isolated by "authenticated user + tenant + agent config key"
- Message actions: copy, edit (loads a user message back into the input), retract (with confirmation), regenerate (last reply only)
- Message timestamps: `H:i` for today, `m-d H:i` for this year, `Y-m-d H:i` otherwise (Filament timezone)
- Composer with an in-field clear button, Enter to send / Shift + Enter for a new line, and IME-safe handling
- Status indicator: green when idle, pulsing amber while generating, red after a failed reply
- Configurable closed-state icon (png / jpg path or full URL)
- Friendly error messages; exception details only when debug is enabled, full stack traces are always logged
- Optional database persistence: when an agent uses `RemembersConversations`, the package reuses `agent_conversations` / `agent_conversation_messages`
- Dark mode, mobile-friendly layout, English and Simplified Chinese translations

## Requirements

- PHP 8.2+
- Filament 5.x
- Livewire 4.4+
- `laravel/ai` 1.3+

## Installation

```bash
composer require jason/filament-chat-assistant
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-chat-assistant-config
```

Optionally publish the views or translations:

```bash
php artisan vendor:publish --tag=filament-chat-assistant-views
php artisan vendor:publish --tag=filament-chat-assistant-translations
```

If you want database-backed conversations, publish and run the `laravel/ai` conversation migrations following its documentation:

```bash
php artisan vendor:publish   # choose the laravel/ai migrations
php artisan migrate
```

## Registering the plugin

Register the plugin in your panel provider:

```php
use Jason\FilamentChatAssistant\FilamentChatAssistantPlugin;

->plugin(FilamentChatAssistantPlugin::make())
```

The plugin will:

- Register the `filament-chat-assistant` Livewire component
- Render the dialog in the panel `BODY_END` hook, only for authenticated users
- Render only when the current panel resolves at least one agent (panels without agents show no button at all)
- Stream agent replies through `ChatAssistantService`

## Configuration

Full `config/chat-assistant.php` example:

```php
return [
    'enabled' => env('FILAMENT_CHAT_ASSISTANT_ENABLED', true),

    'ui' => [
        // Default size in pixels on first open; resized dimensions are stored in localStorage
        'width' => env('FILAMENT_CHAT_ASSISTANT_WIDTH', 600),
        'height' => env('FILAMENT_CHAT_ASSISTANT_HEIGHT', 800),
        'fullscreen' => env('FILAMENT_CHAT_ASSISTANT_FULLSCREEN', true),
        'new_conversation' => env('FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION', true),

        // Closed-state icon: a public-relative path (images/assistant.png) or a full URL; empty = built-in icon
        'toggle_icon' => env('FILAMENT_CHAT_ASSISTANT_TOGGLE_ICON'),
    ],

    // PHP execution time limit for the streaming request (seconds), 0 = unlimited
    'timeout' => (int) env('FILAMENT_CHAT_ASSISTANT_TIMEOUT', 300),

    // Append the exception class and message to error bubbles; defaults to APP_DEBUG
    'debug' => (bool) env('FILAMENT_CHAT_ASSISTANT_DEBUG', env('APP_DEBUG', false)),

    // Flat form: shared by every panel
    'agents' => [
        'order' => App\Ai\Agents\OrderSearch::class,
        'report' => App\Ai\Agents\OrderReport::class,
    ],

    // Per-panel form: keys are panel IDs, '*' is the fallback for unlisted panels
    // 'agents' => [
    //     'backend' => ['order' => App\Ai\Agents\OrderSearch::class],
    //     'tenant' => ['product' => App\Ai\Agents\ProductSearch::class],
    //     '*' => ['order' => App\Ai\Agents\OrderSearch::class],
    // ],
];
```

### Agent configuration

`agents` supports two shapes:

- **Flat**: every panel shares the same agent set
- **Per-panel**: keys are panel IDs (`backend`, `tenant`, ...); the current panel uses its group, falls back to `'*'`, and renders no dialog when neither exists

Array keys identify conversations: **two different keys pointing at the same agent class still get independent conversations**.

The plugin only mounts when the current panel resolves at least one agent, so no separate panel whitelist is needed.

### Environment variables

| Variable | Default | Description |
| --- | --- | --- |
| `FILAMENT_CHAT_ASSISTANT_ENABLED` | `true` | Enable or disable the plugin |
| `FILAMENT_CHAT_ASSISTANT_WIDTH` | `600` | Default width in pixels |
| `FILAMENT_CHAT_ASSISTANT_HEIGHT` | `800` | Default height in pixels |
| `FILAMENT_CHAT_ASSISTANT_FULLSCREEN` | `true` | Allow maximizing |
| `FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION` | `true` | Show the "new conversation" button |
| `FILAMENT_CHAT_ASSISTANT_TOGGLE_ICON` | empty | Closed-state icon path or URL |
| `FILAMENT_CHAT_ASSISTANT_TIMEOUT` | `300` | Streaming request time limit in seconds, `0` = unlimited |
| `FILAMENT_CHAT_ASSISTANT_DEBUG` | follows `APP_DEBUG` | Show exception details in error bubbles |

Published configuration is merged with the package defaults, so you only need to keep the keys you override.

### Error handling

When an AI call fails, the dialog always shows "The AI service is temporarily unavailable. Please try again later." The exception class and message are appended as a quote block only when `chat-assistant.debug` (defaults to `APP_DEBUG`) is enabled. Keep `APP_DEBUG=false` in production to hide all exception details; the full stack trace is still written to the logs through `report()`.

Before streaming, the package calls `set_time_limit(config('chat-assistant.timeout'))` to avoid the default 30-second PHP limit producing an uncatchable `FatalError`. If `set_time_limit` is disabled via `disable_functions`, adjust `max_execution_time` in php.ini / PHP-FPM instead.

## Writing an agent

An agent must implement `Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent`, which extends Laravel AI's `Agent` and additionally requires a name and avatar:

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
        return 'Order assistant';
    }

    public function avatar(): ?string
    {
        return asset('images/avatar.png'); // null falls back to the first character of the name
    }

    public function instructions(): string
    {
        return 'You are an order assistant...';
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

Constructor arguments:

| Argument | Description |
| --- | --- |
| `$user` | The authenticated Filament user |
| `$tenantId` | Current panel tenant ID; `null` on the platform panel |
| `$history` | Previous messages (`Laravel\Ai\Messages\Message[]`), injected only when conversations are not database-backed |

> `$history` must have a default value (e.g. `= []`). When rendering the UI the container only injects `user` and `tenantId`; a required parameter makes the agent fail to construct and be skipped.

`instructions()`, `tools()`, `messages()` and the rest come from the `laravel/ai` contracts; combine them as needed. Changing instructions or tools never affects the plugin itself.

### Database persistence (optional)

To store conversations in the database, let the agent use `laravel/ai`'s `RemembersConversations` (trait + interface):

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

With persistence enabled:

- The AI SDK writes messages to `agent_conversations` / `agent_conversation_messages`
- When a conversation ID exists, the dialog reads those tables directly; "retract" and "regenerate" delete the corresponding rows so the AI context loses them too
- "New conversation" only switches to a new conversation ID; previous conversations stay in the database
- **Do not define `messages()` on the agent**, it would override the trait's database-backed implementation
- Disable automatic title generation (this repository sets `ai.conversations.generate_title = false` in `config/ai.php`) to avoid an extra model call per new conversation

Without this trait the plugin keeps using the session plus the `$history` injected into the constructor.

## UI interactions

### Message actions

Every message has action buttons below the bubble:

| Action | Availability | Behaviour |
| --- | --- | --- |
| Copy | All messages | Copies the raw text (Markdown source for assistant messages); briefly shows "Copied" |
| Edit | User messages | Loads the content back into the input and focuses it; sending appends a new message |
| Retract | All messages | Confirms, then deletes it; with persistence enabled the database row is removed too |
| Regenerate | Last assistant message | Deletes that reply and requests a new one using the preceding user message |

Edit, retract and regenerate are ignored while a reply is in progress (`replying`).

### Time and status

- Timestamps follow the Filament timezone; hovering reveals the full `Y-m-d H:i:s`
- Header status dot: green = idle, pulsing amber = generating, red = last reply failed; the state lives in the component and resets on page reload

### Composer

- The clear and send buttons sit inside the input field
- Enter sends, Shift + Enter adds a new line, IME composition is respected
- Input is limited to 2000 characters; the dialog keeps the latest 50 messages

### Window

- Drag the top, left or top-left edge to resize; `localStorage` stores `tk-assistant-width`, `tk-assistant-height` and `tk-assistant-maximized`
- At 640px wide or less the dialog goes fullscreen and resizing is disabled

## Data and isolation

| Agent type | Message storage | Conversation ID |
| --- | --- | --- |
| Plain `Conversational` | Session (`filament-chat-assistant.messages.*`) | Session |
| `RemembersConversations` | Database (`agent_conversation_messages`) | Session stores only the conversation ID |

- Isolation is by authenticated user + tenant (`platform` for the platform panel) + agent config key
- With persistence enabled the session still keeps a UI cache of up to 50 messages for agent switching and unreplied messages
- Session keys include the agent config key; changing the key starts a fresh local conversation

## Known limitations

- With persistence enabled, the SDK writes the user message when the whole turn completes, so after a reload the user bubble shows the completion time rather than the send time
- "New conversation" does not delete previous database conversations; there is no conversation list or cleanup command yet
- Without `RemembersConversations`, messages live only in the session and disappear when the browser session ends
- Tool-only turns without text output may render an empty assistant bubble in database mode
- Only `zh_CN` and `en` translations are bundled; other locales fall back to the host application's fallback locale
- The `agents` grouping heuristic treats any array value as a group; do not put arrays in a flat configuration

## Testing

The package uses PHPUnit and Orchestra Testbench; tests do not depend on the host application:

```bash
composer install
vendor/bin/phpunit
```

Coverage includes:

- Configuration merging, defaults and translations
- Plugin render conditions: authentication, `enabled`, per-panel / `*` agent resolution
- Component: agent resolution and switching, same-class multi-instance isolation, invalid agent filtering, message trimming, input validation, streaming replies, copy / edit / retract / regenerate, clearing the input, timestamps and status rendering
- Persistence: `RememberedConversation` reads and deletes
- `ChatAssistantService`: history passing, conversation continuation, error fallback, debug detail toggle

> Testbench development dependencies require PHP 8.3+.

## License

MIT. See [LICENSE.md](LICENSE.md).
