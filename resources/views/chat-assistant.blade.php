@php
    $renderMarkdown = static fn (string $content): string => \Illuminate\Support\Str::markdown($content, [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ]);

    $defaultWidth = (int) config('chat-assistant.ui.width', 384);
    $defaultHeight = (int) config('chat-assistant.ui.height', 544);
    $allowFullscreen = (bool) config('chat-assistant.ui.fullscreen', true);
    $showNewConversation = (bool) config('chat-assistant.ui.new_conversation', true);
    $assistantName = (string) ($agentName ?: (config('chat-assistant.ui.title') ?: __('filament-chat-assistant::chat-assistant.title')));
@endphp

<div
    class="tk-assistant"
    x-data="{
        width: Number(localStorage.getItem('tk-assistant-width')) || {{ $defaultWidth }},
        height: Number(localStorage.getItem('tk-assistant-height')) || {{ $defaultHeight }},
        maximized: {{ $allowFullscreen ? "localStorage.getItem('tk-assistant-maximized') === '1'" : 'false' }},
        isMobile: false,
        init() {
            this.isMobile = window.matchMedia('(max-width: 640px)').matches;

            window.matchMedia('(max-width: 640px)').addEventListener('change', (event) => {
                this.isMobile = event.matches;
            });

            @if (! $allowFullscreen)
                this.maximized = false;
                localStorage.removeItem('tk-assistant-maximized');
            @endif

            this.$watch('width', (value) => localStorage.setItem('tk-assistant-width', value));
            this.$watch('height', (value) => localStorage.setItem('tk-assistant-height', value));
        },
        clamp(value, min, max) {
            return Math.min(Math.max(value, min), max);
        },
        startResize(event, axis) {
            event.preventDefault();

            const panel = this.$refs.panel;
            const startX = event.clientX;
            const startY = event.clientY;
            const startWidth = panel.offsetWidth;
            const startHeight = panel.offsetHeight;

            const move = (moveEvent) => {
                if (axis !== 'y') {
                    this.width = this.clamp(startWidth + (startX - moveEvent.clientX), 320, window.innerWidth - 32);
                }

                if (axis !== 'x') {
                    this.height = this.clamp(startHeight + (startY - moveEvent.clientY), 360, window.innerHeight - 120);
                }
            };

            const stop = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', stop);
                document.body.style.userSelect = '';
            };

            document.body.style.userSelect = 'none';
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', stop);
        },
        toggleMaximized() {
            this.maximized = ! this.maximized;
            localStorage.setItem('tk-assistant-maximized', this.maximized ? '1' : '0');
        },
        resetSize() {
            this.maximized = false;
            localStorage.setItem('tk-assistant-maximized', '0');

            this.width = {{ $defaultWidth }};
            this.height = {{ $defaultHeight }};
        },
    }"
    x-on:chat-reply-requested.window="$wire.requestReply()"
>
    <button
        type="button"
        class="tk-assistant__toggle"
        wire:click="toggle"
        x-show="! maximized || ! $wire.open"
        aria-label="{{ $assistantName }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        title="{{ $open ? __('filament-chat-assistant::chat-assistant.close', ['name' => $assistantName]) : __('filament-chat-assistant::chat-assistant.open', ['name' => $assistantName]) }}"
    >
        @if ($open)
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8V4H8" />
                <rect x="4" y="8" width="16" height="12" rx="2" stroke-linecap="round" stroke-linejoin="round" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M2 14h2M20 14h2M15 13v2M9 13v2" />
            </svg>
        @endif
    </button>

    @if ($open)
        <section
            x-ref="panel"
            class="tk-assistant__panel"
            :class="{ 'tk-assistant__panel--maximized': maximized }"
            :style="(maximized || isMobile) ? '' : ('width: ' + width + 'px; height: ' + height + 'px')"
            role="dialog"
            aria-label="{{ $assistantName }}"
        >
            <div class="tk-assistant__resize tk-assistant__resize--top" x-on:pointerdown="startResize($event, 'y')" aria-hidden="true"></div>
            <div class="tk-assistant__resize tk-assistant__resize--left" x-on:pointerdown="startResize($event, 'x')" aria-hidden="true"></div>
            <div class="tk-assistant__resize tk-assistant__resize--corner" x-on:pointerdown="startResize($event, 'both')" aria-hidden="true"></div>

            <header class="tk-assistant__header">
                <div class="tk-assistant__title" x-data="{ menu: false }" x-on:click.outside="menu = false">
                    @if (count($agents) > 1)
                        <button
                            type="button"
                            class="tk-assistant__agent-toggle"
                            x-on:click="menu = ! menu"
                            aria-haspopup="listbox"
                            :aria-expanded="menu"
                            title="{{ __('filament-chat-assistant::chat-assistant.switch_agent') }}"
                        >
                            @if ($agentAvatar)
                                <img src="{{ $agentAvatar }}" alt="{{ $assistantName }}" class="tk-assistant__avatar">
                            @else
                                <span class="tk-assistant__avatar tk-assistant__avatar--fallback">{{ mb_substr($assistantName, 0, 1) }}</span>
                            @endif

                            <span class="tk-assistant__agent-name">{{ $assistantName }}</span>

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <div class="tk-assistant__agent-menu" x-show="menu" x-cloak role="listbox">
                            @foreach ($agents as $index => $agent)
                                <button
                                    type="button"
                                    role="option"
                                    class="tk-assistant__agent-option {{ $agent['class'] === $agentClass ? 'tk-assistant__agent-option--active' : '' }}"
                                    wire:click="selectAgent({{ $index }})"
                                    x-on:click="menu = false"
                                    aria-selected="{{ $agent['class'] === $agentClass ? 'true' : 'false' }}"
                                >
                                    @if ($agent['avatar'])
                                        <img src="{{ $agent['avatar'] }}" alt="{{ $agent['name'] }}" class="tk-assistant__avatar">
                                    @else
                                        <span class="tk-assistant__avatar tk-assistant__avatar--fallback">{{ mb_substr($agent['name'], 0, 1) }}</span>
                                    @endif

                                    <span>{{ $agent['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        @if ($agentAvatar)
                            <img src="{{ $agentAvatar }}" alt="{{ $assistantName }}" class="tk-assistant__avatar">
                        @else
                            <span class="tk-assistant__avatar tk-assistant__avatar--fallback">{{ mb_substr($assistantName, 0, 1) }}</span>
                        @endif

                        <span>{{ $assistantName }}</span>
                    @endif

                    <span class="tk-assistant__status" aria-hidden="true"></span>
                </div>

                <div class="tk-assistant__actions">
                    @if ($allowFullscreen)
                        <button
                            type="button"
                            class="tk-assistant__icon"
                            x-show="! maximized"
                            x-on:click="toggleMaximized()"
                            title="{{ __('filament-chat-assistant::chat-assistant.maximize') }}"
                            aria-label="{{ __('filament-chat-assistant::chat-assistant.maximize') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="tk-assistant__icon"
                            x-show="maximized"
                            x-on:click="toggleMaximized()"
                            title="{{ __('filament-chat-assistant::chat-assistant.restore') }}"
                            aria-label="{{ __('filament-chat-assistant::chat-assistant.restore') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25" />
                            </svg>
                        </button>
                    @endif

                    <button
                        type="button"
                        class="tk-assistant__icon"
                        x-on:click="resetSize()"
                        title="{{ __('filament-chat-assistant::chat-assistant.reset_size') }}"
                        aria-label="{{ __('filament-chat-assistant::chat-assistant.reset_size') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </button>

                    @if ($showNewConversation)
                        <button
                            type="button"
                            class="tk-assistant__icon"
                            wire:click="resetConversation"
                            title="{{ __('filament-chat-assistant::chat-assistant.new_conversation') }}"
                            aria-label="{{ __('filament-chat-assistant::chat-assistant.new_conversation') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m0 4.992-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7" />
                            </svg>
                        </button>
                    @endif

                    <button
                        type="button"
                        class="tk-assistant__icon"
                        wire:click="toggle"
                        title="{{ __('filament-chat-assistant::chat-assistant.collapse') }}"
                        aria-label="{{ __('filament-chat-assistant::chat-assistant.collapse') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                        </svg>
                    </button>
                </div>
            </header>

            @if (! $agentConfigured)
                <div class="tk-assistant__notice">
                    {{ __('filament-chat-assistant::chat-assistant.agent_missing') }}
                </div>
            @endif

            <div
                class="tk-assistant__messages"
                x-data
                x-init="
                    const container = $el;
                    const scrollToBottom = () => { container.scrollTop = container.scrollHeight; };

                    new MutationObserver(scrollToBottom).observe(container, {
                        childList: true,
                        subtree: true,
                        characterData: true,
                    });

                    scrollToBottom();
                "
            >
                @forelse ($messages as $index => $message)
                    <div
                        class="tk-assistant__row tk-assistant__row--{{ $message['role'] }}"
                        wire:key="chat-message-{{ $index }}"
                    >
                        @if ($message['role'] === 'assistant')
                            @if ($agentAvatar)
                                <img src="{{ $agentAvatar }}" alt="{{ $assistantName }}" class="tk-assistant__message-avatar">
                            @else
                                <span class="tk-assistant__message-avatar tk-assistant__avatar--fallback">{{ mb_substr($assistantName, 0, 1) }}</span>
                            @endif
                        @endif

                        <div class="tk-assistant__message">
                            <div class="tk-assistant__message-name">
                                {{ $message['role'] === 'assistant' ? $assistantName : $userName }}
                            </div>

                            <div class="tk-assistant__bubble">
                                @if ($message['role'] === 'assistant')
                                    {!! $renderMarkdown($message['content']) !!}
                                @else
                                    {!! nl2br(e($message['content'])) !!}
                                @endif
                            </div>
                        </div>

                        @if ($message['role'] === 'user')
                            @if ($userAvatar)
                                <img src="{{ $userAvatar }}" alt="{{ $userName }}" class="tk-assistant__message-avatar">
                            @else
                                <span class="tk-assistant__message-avatar tk-assistant__avatar--fallback">{{ mb_substr($userName, 0, 1) }}</span>
                            @endif
                        @endif
                    </div>
                @empty
                    <p class="tk-assistant__empty">{{ __('filament-chat-assistant::chat-assistant.empty', ['name' => $assistantName]) }}</p>
                @endforelse

                {{-- 等待 AI 回复时的加载动画 --}}
                <div
                    class="tk-assistant__row tk-assistant__row--assistant tk-assistant__typing"
                    wire:loading.flex
                    wire:target="requestReply"
                >
                    @if ($agentAvatar)
                        <img src="{{ $agentAvatar }}" alt="{{ $assistantName }}" class="tk-assistant__message-avatar">
                    @else
                        <span class="tk-assistant__message-avatar tk-assistant__avatar--fallback">{{ mb_substr($assistantName, 0, 1) }}</span>
                    @endif

                    <div class="tk-assistant__bubble tk-assistant__typing-bubble" aria-label="{{ __('filament-chat-assistant::chat-assistant.thinking') }}">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                {{-- 流式输出目标：仅在生成时可见，结束后由最终渲染替换 --}}
                <div class="tk-assistant__row tk-assistant__row--assistant tk-assistant__row--draft">
                    <div class="tk-assistant__bubble tk-assistant__bubble--stream" wire:stream="chat-stream"></div>
                </div>
            </div>

            <form class="tk-assistant__composer" wire:submit="sendMessage">
                <textarea
                    class="tk-assistant__input"
                    wire:model="question"
                    rows="1"
                    maxlength="2000"
                    placeholder="{{ __('filament-chat-assistant::chat-assistant.placeholder') }}"
                    aria-label="{{ __('filament-chat-assistant::chat-assistant.message_aria') }}"
                    @disabled(! $agentConfigured)
                    x-data="{
                        resize() {
                            const el = $el;
                            const borders = el.offsetHeight - el.clientHeight;

                            el.style.height = 'auto';

                            const height = el.scrollHeight + borders;

                            el.style.height = Math.min(height, 120) + 'px';
                            el.style.overflowY = height > 120 ? 'auto' : 'hidden';
                        },
                    }"
                    x-init="resize()"
                    x-on:input="resize()"
                    x-on:chat-reply-requested.window="resize()"
                    x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $wire.sendMessage(); }"
                ></textarea>

                <button
                    type="submit"
                    class="tk-assistant__send"
                    title="{{ __('filament-chat-assistant::chat-assistant.send') }}"
                    aria-label="{{ __('filament-chat-assistant::chat-assistant.send') }}"
                    @disabled(! $agentConfigured)
                    wire:loading.attr="disabled"
                    wire:target="sendMessage,requestReply"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </button>
            </form>

            @error('question')
                <p class="tk-assistant__error">{{ $message }}</p>
            @enderror
        </section>
    @endif

    <style>
        .tk-assistant {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            z-index: 40;
            font-size: .875rem;
        }

        .tk-assistant__toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            margin-left: auto;
            border: 0;
            border-radius: 9999px;
            background: var(--primary-600, #4f46e5);
            color: #fff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .24);
            cursor: pointer;
            transition: transform .15s ease;
        }

        .tk-assistant__toggle:hover {
            transform: translateY(-1px);
        }

        .tk-assistant__toggle svg {
            width: 1.4rem;
            height: 1.4rem;
        }

        .tk-assistant__panel {
            position: fixed;
            right: 1.25rem;
            bottom: 4.75rem;
            display: flex;
            flex-direction: column;
            width: min(24rem, calc(100vw - 2.5rem));
            height: min(34rem, calc(100vh - 8rem));
            max-width: calc(100vw - 1rem);
            max-height: calc(100vh - 1rem);
            overflow: hidden;
            border: 1px solid rgba(127, 127, 127, .22);
            border-radius: 1rem;
            background: #fff;
            color: #18181b;
            box-shadow: 0 20px 45px rgba(0, 0, 0, .22);
        }

        .tk-assistant__panel--maximized {
            top: .75rem;
            right: .75rem;
            bottom: .75rem;
            left: .75rem;
            width: auto;
            height: auto;
            max-width: none;
            max-height: none;
        }

        .tk-assistant__resize {
            position: absolute;
            z-index: 1;
            touch-action: none;
        }

        .tk-assistant__resize--top {
            top: 0;
            right: 14px;
            left: 14px;
            height: 8px;
            cursor: ns-resize;
        }

        .tk-assistant__resize--left {
            top: 14px;
            bottom: 14px;
            left: 0;
            width: 8px;
            cursor: ew-resize;
        }

        .tk-assistant__resize--corner {
            top: 0;
            left: 0;
            width: 16px;
            height: 16px;
            cursor: nwse-resize;
        }

        .tk-assistant__resize--corner::after {
            content: '';
            position: absolute;
            top: 4px;
            left: 4px;
            width: 8px;
            height: 8px;
            border-top: 2px solid rgba(127, 127, 127, .55);
            border-left: 2px solid rgba(127, 127, 127, .55);
            border-top-left-radius: 2px;
            opacity: .35;
        }

        .tk-assistant__panel:hover .tk-assistant__resize--corner::after {
            opacity: .75;
        }

        .dark .tk-assistant__panel {
            border-color: rgba(255, 255, 255, .12);
            background: #18181b;
            color: #f4f4f5;
        }

        .tk-assistant__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .75rem 1rem;
            border-bottom: 1px solid rgba(127, 127, 127, .18);
        }

        .tk-assistant__title {
            position: relative;
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 0;
            font-weight: 600;
        }

        .tk-assistant__agent-toggle {
            display: flex;
            align-items: center;
            gap: .4rem;
            max-width: 14rem;
            min-width: 0;
            padding: .15rem .35rem;
            border: 0;
            border-radius: .5rem;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
        }

        .tk-assistant__agent-toggle:hover {
            background: rgba(127, 127, 127, .14);
        }

        .tk-assistant__agent-toggle svg {
            width: .85rem;
            height: .85rem;
            flex: none;
            opacity: .6;
        }

        .tk-assistant__agent-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tk-assistant__agent-menu {
            position: absolute;
            top: calc(100% + .35rem);
            left: 0;
            z-index: 20;
            display: flex;
            flex-direction: column;
            gap: .1rem;
            min-width: 12rem;
            max-width: 18rem;
            padding: .25rem;
            border: 1px solid rgba(127, 127, 127, .22);
            border-radius: .6rem;
            background: #fff;
            box-shadow: 0 12px 28px rgba(0, 0, 0, .18);
        }

        .dark .tk-assistant__agent-menu {
            border-color: rgba(255, 255, 255, .12);
            background: #18181b;
        }

        .tk-assistant__agent-menu[x-cloak] {
            display: none !important;
        }

        .tk-assistant__agent-option {
            display: flex;
            align-items: center;
            gap: .45rem;
            width: 100%;
            padding: .35rem .45rem;
            border: 0;
            border-radius: .45rem;
            background: transparent;
            color: inherit;
            font: inherit;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
        }

        .tk-assistant__agent-option:hover {
            background: rgba(127, 127, 127, .14);
        }

        .tk-assistant__agent-option--active {
            background: rgba(79, 70, 229, .12);
        }

        .tk-assistant__status {
            width: .5rem;
            height: .5rem;
            border-radius: 9999px;
            background: #22c55e;
        }

        .tk-assistant__avatar {
            width: 1.5rem;
            height: 1.5rem;
            flex: none;
            border-radius: 9999px;
            object-fit: cover;
        }

        .tk-assistant__avatar--fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-600, #4f46e5);
            color: #fff;
            font-size: .65rem;
            font-weight: 600;
        }

        .tk-assistant__message-avatar {
            width: 1.6rem;
            height: 1.6rem;
            flex: none;
            border-radius: 9999px;
            object-fit: cover;
        }

        .tk-assistant__actions {
            display: flex;
            align-items: center;
            gap: .25rem;
        }

        .tk-assistant__icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border: 0;
            border-radius: .5rem;
            background: transparent;
            color: inherit;
            opacity: .7;
            cursor: pointer;
        }

        .tk-assistant__icon:hover {
            background: rgba(127, 127, 127, .14);
            opacity: 1;
        }

        .tk-assistant__icon svg {
            width: 1rem;
            height: 1rem;
        }

        .tk-assistant__notice {
            margin: .75rem 1rem 0;
            padding: .5rem .7rem;
            border: 1px solid rgba(245, 158, 11, .35);
            border-radius: .5rem;
            background: rgba(245, 158, 11, .12);
            color: #b45309;
            font-size: .75rem;
            line-height: 1.5;
        }

        .dark .tk-assistant__notice {
            color: #fbbf24;
            border-color: rgba(251, 191, 36, .35);
            background: rgba(251, 191, 36, .12);
        }

        .tk-assistant__messages {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: .625rem;
            overflow-y: auto;
            padding: .875rem 1rem;
            scroll-behavior: smooth;
        }

        .tk-assistant__row {
            display: flex;
            align-items: flex-start;
            gap: .375rem;
        }

        .tk-assistant__row--user {
            justify-content: flex-end;
        }

        .tk-assistant__message {
            display: flex;
            flex-direction: column;
            gap: .2rem;
            max-width: 85%;
            min-width: 0;
        }

        .tk-assistant__row--user .tk-assistant__message {
            align-items: flex-end;
        }

        .tk-assistant__message-name {
            padding-inline: .2rem;
            font-size: .7rem;
            line-height: 1.4;
            opacity: .6;
        }

        .tk-assistant__bubble {
            max-width: 100%;
            padding: .55rem .8rem;
            border-radius: 1rem;
            border-top-left-radius: .25rem;
            background: rgba(127, 127, 127, .14);
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .tk-assistant__row--user .tk-assistant__bubble {
            border-top-left-radius: 1rem;
            border-top-right-radius: .25rem;
            background: var(--primary-600, #4f46e5);
            color: #fff;
        }

        .tk-assistant__bubble > :first-child {
            margin-top: 0;
        }

        .tk-assistant__bubble > :last-child {
            margin-bottom: 0;
        }

        .tk-assistant__bubble p {
            margin: 0 0 .5rem;
        }

        .tk-assistant__bubble ul,
        .tk-assistant__bubble ol {
            margin: .25rem 0 .5rem;
            padding-left: 1.25rem;
        }

        .tk-assistant__bubble ul {
            list-style: disc;
        }

        .tk-assistant__bubble ol {
            list-style: decimal;
        }

        .tk-assistant__bubble li + li {
            margin-top: .15rem;
        }

        .tk-assistant__bubble h1,
        .tk-assistant__bubble h2,
        .tk-assistant__bubble h3,
        .tk-assistant__bubble h4 {
            margin: .6rem 0 .3rem;
            font-size: .9375rem;
            font-weight: 600;
        }

        .tk-assistant__bubble code {
            padding: .1rem .3rem;
            border-radius: .25rem;
            background: rgba(127, 127, 127, .22);
            font-size: .8125rem;
        }

        .tk-assistant__bubble pre {
            margin: .5rem 0;
            padding: .5rem .6rem;
            overflow-x: auto;
            border-radius: .5rem;
            background: rgba(127, 127, 127, .16);
        }

        .tk-assistant__bubble pre code {
            padding: 0;
            background: none;
        }

        .tk-assistant__bubble table {
            display: block;
            width: 100%;
            margin: .5rem 0;
            overflow-x: auto;
            border-collapse: collapse;
            font-size: .8125rem;
        }

        .tk-assistant__bubble th,
        .tk-assistant__bubble td {
            padding: .3rem .45rem;
            border: 1px solid rgba(127, 127, 127, .3);
            text-align: left;
        }

        .tk-assistant__bubble th {
            background: rgba(127, 127, 127, .12);
            font-weight: 600;
        }

        .tk-assistant__bubble blockquote {
            margin: .5rem 0;
            padding-left: .6rem;
            border-left: 3px solid rgba(127, 127, 127, .4);
            opacity: .85;
        }

        .tk-assistant__bubble a {
            color: var(--primary-600, #4f46e5);
            text-decoration: underline;
        }

        .tk-assistant__row--user .tk-assistant__bubble a {
            color: inherit;
        }

        .tk-assistant__bubble hr {
            margin: .6rem 0;
            border: 0;
            border-top: 1px solid rgba(127, 127, 127, .25);
        }

        .tk-assistant__row--draft:has(.tk-assistant__bubble--stream:empty) {
            display: none;
        }

        .tk-assistant__typing {
            display: none;
        }

        .tk-assistant__typing-bubble {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .7rem .85rem;
        }

        .tk-assistant__typing-bubble span {
            width: .4rem;
            height: .4rem;
            border-radius: 9999px;
            background: currentColor;
            opacity: .35;
            animation: tk-assistant-typing 1.1s infinite ease-in-out;
        }

        .tk-assistant__typing-bubble span:nth-child(2) {
            animation-delay: .15s;
        }

        .tk-assistant__typing-bubble span:nth-child(3) {
            animation-delay: .3s;
        }

        @keyframes tk-assistant-typing {
            0%, 80%, 100% {
                transform: translateY(0);
                opacity: .35;
            }

            40% {
                transform: translateY(-3px);
                opacity: 1;
            }
        }

        .tk-assistant__empty {
            padding: 1rem 0;
            text-align: center;
            opacity: .6;
        }

        .tk-assistant__composer {
            display: flex;
            align-items: flex-end;
            gap: .5rem;
            padding: .75rem;
            border-top: 1px solid rgba(127, 127, 127, .18);
        }

        .tk-assistant__input {
            flex: 1;
            min-height: 2.5rem;
            max-height: 7.5rem;
            padding: .55rem .75rem;
            border: 1px solid rgba(127, 127, 127, .28);
            border-radius: .75rem;
            background: transparent;
            color: inherit;
            font: inherit;
            line-height: 1.5;
            outline: none;
            overflow-y: hidden;
            resize: none;
        }

        .tk-assistant__input:focus {
            border-color: var(--primary-600, #4f46e5);
            box-shadow: 0 0 0 1px var(--primary-600, #4f46e5);
        }

        .tk-assistant__send {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border: 0;
            border-radius: .75rem;
            background: var(--primary-600, #4f46e5);
            color: #fff;
            cursor: pointer;
        }

        .tk-assistant__send[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .tk-assistant__send svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .tk-assistant__error {
            margin: 0;
            padding: 0 .75rem .5rem;
            color: #dc2626;
            font-size: .75rem;
        }

        @media (max-width: 640px) {
            .tk-assistant {
                right: .75rem;
                bottom: .75rem;
            }

            .tk-assistant__panel {
                top: .75rem;
                right: .75rem;
                bottom: 4.5rem;
                left: .75rem;
                width: auto;
                height: auto;
                max-width: none;
                max-height: none;
            }

            .tk-assistant__resize {
                display: none;
            }
        }
    </style>
</div>
