<?php

namespace Jason\FilamentChatAssistant\Livewire;

use Illuminate\Contracts\View\View;
use Jason\FilamentChatAssistant\Contracts\ChatAssistantAgent;
use Jason\FilamentChatAssistant\Services\ChatAssistantService;
use Jason\FilamentChatAssistant\Support\PanelAgents;
use Jason\FilamentChatAssistant\Support\RememberedConversation;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Filament 面板右下角的 AI 助手对话框
 *
 * 对话按「登录用户 + 租户 + Agent」隔离，Agent 需实现 ChatAssistantAgent。
 */
class ChatAssistant extends Component
{
    public bool $open = false;

    public bool $replying = false;

    /**
     * 最近一次回复是否失败，用于标题栏状态指示器
     */
    public bool $hasError = false;

    /** @var array<int, array{key: string, class: string, name: string, avatar: string|null}> */
    public array $agents = [];

    public bool $agentConfigured = false;

    public ?string $agentClass = null;

    public string $agentName = '';

    public ?string $agentAvatar = null;

    public string $userName = '';

    public ?string $userAvatar = null;

    #[Validate('required|string|max:2000')]
    public string $question = '';

    /**
     * @var array<int, array{id?: string, role: string, content: string, created_at?: string}>
     */
    public array $messages = [];

    public function mount(): void
    {
        abort_unless(filament()->auth()->check(), 403);

        $this->resolveAgents();
        $this->resolveUserMeta();
        $this->restoreActiveAgent();

        $this->open = (bool) session($this->openSessionKey(), false);
        $this->messages = $this->loadMessages();
    }

    public function render(): View
    {
        return view('filament-chat-assistant::chat-assistant');
    }

    public function toggle(): void
    {
        $this->open = !$this->open;

        session([$this->openSessionKey() => $this->open]);
    }

    /**
     * 切换到指定 Agent，会话记录与 Agent 一一对应
     */
    public function selectAgent(int $index): void
    {
        if ($this->replying || !isset($this->agents[$index])) {
            return;
        }

        $agent = $this->agents[$index];

        if ($agent['class'] === $this->agentClass) {
            return;
        }

        $this->persistMessages();

        $this->applyAgent($agent);

        session([$this->agentSessionKey() => $this->agentClass]);

        $this->messages = $this->loadMessages();
        $this->persistMessages();
    }

    public function resetConversation(): void
    {
        $this->messages = [];
        $this->question = '';

        session()->forget([
            $this->messageSessionKey(),
            $this->conversationSessionKey(),
        ]);
    }

    /**
     * 把指定用户消息放入输入框，便于修改后重新发送
     */
    public function editMessage(int $index): void
    {
        if ($this->replying) {
            return;
        }

        $message = $this->messages[$index] ?? null;

        if ($message === null || ($message['role'] ?? null) !== 'user') {
            return;
        }

        $this->question = (string) ($message['content'] ?? '');

        $this->dispatch('chat-edit-message');
    }

    /**
     * 先落库用户消息并渲染，再通过前端事件触发答复请求。
     */
    public function sendMessage(): void
    {
        if ($this->replying || !$this->agentConfigured) {
            return;
        }

        $this->validate();

        $this->messages[] = [
            'role' => 'user',
            'content' => trim($this->question),
            'created_at' => now()->toIso8601String(),
        ];

        $this->question = '';

        $this->trimMessages();
        $this->persistMessages();

        $this->dispatch('chat-reply-requested');
    }

    /**
     * 清空输入框
     */
    public function clearQuestion(): void
    {
        $this->question = '';

        $this->resetValidation('question');

        $this->dispatch('chat-input-cleared');
    }

    /**
     * 撤回单条消息（同时从后续 AI 上下文中移除）
     */
    public function retractMessage(int $index): void
    {
        if ($this->replying || !array_key_exists($index, $this->messages)) {
            return;
        }

        $message = $this->messages[$index];

        if ($this->remembersConversation() && !empty($message['id'])) {
            RememberedConversation::delete($this->conversationId(), $message['id']);

            $this->messages = $this->loadMessages();
            $this->persistMessages();
        } else {
            array_splice($this->messages, $index, 1);

            $this->persistMessages();
        }

        $this->hasError = false;
    }

    /**
     * 撤回最后一条助手回复，并用上一条用户消息重新生成
     */
    public function regenerateReply(): void
    {
        if ($this->replying || $this->agentClass === null || $this->messages === []) {
            return;
        }

        $last = $this->messages[array_key_last($this->messages)];

        if (($last['role'] ?? null) !== 'assistant') {
            return;
        }

        if ($this->remembersConversation()) {
            if (!empty($last['id'])) {
                RememberedConversation::deleteLatestAssistant($this->conversationId());
            }

            $this->messages = $this->loadMessages();
            $this->persistMessages();
        } else {
            array_pop($this->messages);
        }

        if ($this->messages === []) {
            $this->persistMessages();

            return;
        }

        $previous = $this->messages[array_key_last($this->messages)];

        $this->persistMessages();

        if (($previous['role'] ?? null) !== 'user') {
            return;
        }

        $this->dispatch('chat-reply-requested');
    }

    /**
     * 流式生成助手回复，单独成一次请求，保证用户消息先显示。
     */
    public function requestReply(): void
    {
        if ($this->replying || $this->messages === [] || $this->agentClass === null) {
            return;
        }

        $last = $this->messages[array_key_last($this->messages)];

        if ($last['role'] !== 'user') {
            return;
        }

        $user = filament()->auth()->user();

        if ($user === null) {
            return;
        }

        $this->replying = true;
        $this->hasError = false;

        // PHP 默认 30 秒执行上限会让长连接抛出不可捕获的 FatalError，
        // 这里先放宽，超时才会以普通异常的形式回到 catch 并在对话框里展示。
        $this->applyExecutionTimeLimit();

        $service = app(ChatAssistantService::class);

        try {
            $stream = $service->stream(
                messages: $this->messages,
                user: $user,
                tenantId: filament()->getTenant()?->getKey(),
                conversationId: session($this->conversationSessionKey()),
                agentClass: $this->agentClass,
            );

            $reply = '';

            foreach ($stream as $chunk) {
                $this->stream(nl2br(e($chunk)), to: 'chat-stream');

                $reply .= $chunk;
            }

            if (($conversationId = $stream->getReturn()) !== null) {
                session([$this->conversationSessionKey() => $conversationId]);
            }

            $exception = $service->lastException();
            $this->hasError = $exception !== null;

            if ($this->remembersConversation()) {
                $stored = $this->loadMessages();

                if ($stored !== []) {
                    $this->messages = $stored;
                } else {
                    $this->messages[] = [
                        'role' => 'assistant',
                        'content' => trim($reply) !== '' ? $reply : __('filament-chat-assistant::chat-assistant.error_service'),
                        'created_at' => now()->toIso8601String(),
                    ];
                }

                if ($exception !== null) {
                    $this->messages[] = [
                        'role' => 'assistant',
                        'content' => $service->errorMessage($exception),
                        'created_at' => now()->toIso8601String(),
                    ];
                }

                $this->trimMessages();
                $this->persistMessages();
            } else {
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => trim($reply) !== '' ? $reply : __('filament-chat-assistant::chat-assistant.error_service'),
                    'created_at' => now()->toIso8601String(),
                ];

                $this->trimMessages();
                $this->persistMessages();
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->hasError = true;

            if ($this->remembersConversation()) {
                $stored = $this->loadMessages();

                if ($stored !== []) {
                    $this->messages = $stored;
                }
            }

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $service->errorMessage($exception),
                'created_at' => now()->toIso8601String(),
            ];

            $this->trimMessages();
            $this->persistMessages();
        } finally {
            $this->replying = false;
        }
    }

    /**
     * 放宽本次流式请求的 PHP 执行时间上限
     */
    private function applyExecutionTimeLimit(): void
    {
        $timeout = (int) config('chat-assistant.timeout', 300);

        if ($timeout > 0) {
            @set_time_limit($timeout);
        }
    }

    private function trimMessages(): void
    {
        $this->messages = array_slice($this->messages, -50);
    }

    private function persistMessages(): void
    {
        // 落库模式下这里只做 UI 缓存：真正的消息由 AI SDK 写入会话表
        session([$this->messageSessionKey() => $this->messages]);
    }

    /**
     * 当前 Agent 是否把会话落库（RemembersConversations）
     */
    private function remembersConversation(): bool
    {
        return RememberedConversation::supports($this->agentClass);
    }

    private function conversationId(): ?string
    {
        $conversationId = session($this->conversationSessionKey());

        return is_string($conversationId) ? $conversationId : null;
    }

    /**
     * 加载消息：落库模式读 AI SDK 会话表，否则读 session
     *
     * @return array<int, array{id?: string, role: string, content: string, created_at?: string}>
     */
    private function loadMessages(): array
    {
        if ($this->remembersConversation() && ($conversationId = $this->conversationId()) !== null) {
            return RememberedConversation::messages($conversationId);
        }

        return session($this->messageSessionKey(), []);
    }

    /**
     * 解析配置中可用的 Agent 列表
     */
    private function resolveAgents(): void
    {
        $user = filament()->auth()->user();

        if ($user === null) {
            return;
        }

        $configured = config('chat-assistant.agents', []);

        $agents = is_array($configured)
            ? PanelAgents::forPanel($configured, filament()->getCurrentPanel()?->getId())
            : [];

        if ($agents === []) {
            return;
        }

        $tenantId = filament()->getTenant()?->getKey();

        foreach ($agents as $key => $agentClass) {
            if (!is_string($agentClass) || !class_exists($agentClass)) {
                continue;
            }

            if (!is_subclass_of($agentClass, ChatAssistantAgent::class)) {
                continue;
            }

            try {
                $agent = app($agentClass, [
                    'user' => $user,
                    'tenantId' => $tenantId,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            $this->agents[] = [
                'key' => is_string($key) ? $key : $agentClass,
                'class' => $agentClass,
                'name' => $agent->name(),
                'avatar' => $agent->avatar(),
            ];
        }

        $this->agentConfigured = $this->agents !== [];
    }

    /**
     * 恢复上次选中的 Agent，默认使用第一个可用 Agent
     */
    private function restoreActiveAgent(): void
    {
        $stored = session($this->agentSessionKey());

        $active = $this->agents[0] ?? null;

        foreach ($this->agents as $agent) {
            if ($agent['class'] === $stored) {
                $active = $agent;

                break;
            }
        }

        if ($active !== null) {
            $this->applyAgent($active);
        }
    }

    /**
     * @param  array{key: string, class: string, name: string, avatar: string|null}  $agent
     */
    private function applyAgent(array $agent): void
    {
        $this->agentClass = $agent['class'];
        $this->agentName = $agent['name'];
        $this->agentAvatar = $agent['avatar'];
    }

    /**
     * 当前登录用户的名称与头像
     */
    private function resolveUserMeta(): void
    {
        $user = filament()->auth()->user();

        if ($user === null) {
            return;
        }

        $this->userName = (string) ($user->name ?? $user->username ?? '');

        $this->userAvatar = method_exists($user, 'getFilamentAvatarUrl')
            ? $user->getFilamentAvatarUrl()
            : null;
    }

    private function agentSessionKey(): string
    {
        return 'filament-chat-assistant.agent.'.$this->contextKey();
    }

    private function messageSessionKey(): string
    {
        return 'filament-chat-assistant.messages.'.$this->contextKey().'.'.$this->agentHash();
    }

    private function conversationSessionKey(): string
    {
        return 'filament-chat-assistant.conversation.'.$this->contextKey().'.'.$this->agentHash();
    }

    private function openSessionKey(): string
    {
        return 'filament-chat-assistant.open.'.$this->contextKey();
    }

    /**
     * 按 Agent 隔离会话，避免切换 Agent 后串上下文
     */
    private function agentHash(): string
    {
        return substr(md5($this->agentClass ?? 'default'), 0, 12);
    }

    /**
     * 按「登录用户 + 租户」隔离会话，避免切换租户后串上下文
     */
    private function contextKey(): string
    {
        $tenantId = filament()->getTenant()?->getKey() ?? 'platform';

        return filament()->auth()->id().'.'.$tenantId;
    }
}
