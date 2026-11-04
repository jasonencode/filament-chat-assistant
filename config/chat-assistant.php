<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 是否启用
    |--------------------------------------------------------------------------
    */
    'enabled' => env('FILAMENT_CHAT_ASSISTANT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 界面配置
    |--------------------------------------------------------------------------
    |
    | width / height：首次打开时的默认尺寸（像素），用户拖拽后的尺寸会保存在
    | localStorage，点击「还原默认尺寸」会回到这里配置的值。
    |
    | fullscreen：是否允许最大化。
    | new_conversation：是否显示「开始新会话」按钮。
    |
    */
    'ui' => [
        'title' => env('FILAMENT_CHAT_ASSISTANT_TITLE'),
        'width' => env('FILAMENT_CHAT_ASSISTANT_WIDTH', 600),
        'height' => env('FILAMENT_CHAT_ASSISTANT_HEIGHT', 800),
        'fullscreen' => env('FILAMENT_CHAT_ASSISTANT_FULLSCREEN', true),
        'new_conversation' => env('FILAMENT_CHAT_ASSISTANT_NEW_CONVERSATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Agents
    |--------------------------------------------------------------------------
    |
    | 可配置一个或多个 Agent，对话框中可以在它们之间切换。
    | 每个 Agent 需实现 ChatAssistantAgent，构造函数需接受 user 与 tenantId：
    |
    | 'agents' => [
    |     'order' => App\Ai\Agents\OrderSearch::class,
    |     'product' => App\Ai\Agents\ProductSearch::class,
    | ],
    |
    | 数组键用于区分会话记录，可省略（省略时直接使用类名）。
    |
    */
    'agents' => [],

    /*
    |--------------------------------------------------------------------------
    | 面板白名单
    |--------------------------------------------------------------------------
    |
    | 为空表示所有挂载了插件的面板；填写面板 ID（如 backend / tenant）可限制。
    |
    */
    'panels' => [],
];
