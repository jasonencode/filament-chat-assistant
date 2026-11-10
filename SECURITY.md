# Security Policy

## Supported versions

Security fixes are provided for the latest released major version. Older major
versions are not maintained; upgrading to the latest release is the recommended
way to receive a fix.

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| < 2.0   | :x:                |

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report privately through GitHub's private vulnerability reporting:

- https://github.com/jasonencode/filament-chat-assistant/security/advisories/new

If you cannot use GitHub, email **20511924@qq.com** with:

- the affected version,
- a description of the issue and its impact,
- reproduction steps or a proof of concept,
- any suggested fix.

## What to expect

- We aim to acknowledge a report within **3 business days**.
- We will confirm whether the issue is in scope, keep you updated while a fix is
  prepared, and credit you in the advisory unless you prefer to stay anonymous.
- Please give us a reasonable window to release a fix before any public
  disclosure.

## Scope

In scope:

- the plugin's PHP classes, Blade views and JavaScript shipped in `resources/`,
- conversation isolation between users, tenants and agents,
- authorization and data exposure in the bundled Livewire component.

Out of scope:

- vulnerabilities in `laravel/ai`, Filament, Livewire or other dependencies —
  report those to the upstream project,
- misconfiguration of the host application, including exposing `APP_DEBUG`,
  missing authorization on panels, or agents whose tools are themselves unsafe,
- issues that require an already compromised server or database.

## 中文说明

请不要通过公开 issue 报告安全问题。请使用 GitHub 的私密漏洞报告入口，或发送邮件到
**20511924@qq.com**，并在邮件中附上受影响版本、问题描述、复现步骤与影响范围。我们会在
3 个工作日内回复确认，并在修复发布后于安全公告中致谢。
