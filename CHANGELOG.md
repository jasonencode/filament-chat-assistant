# Changelog

All notable changes to `jason/filament-chat-assistant` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-11-05

### Breaking Changes

- `ChatAssistantAgent` now extends `Laravel\Ai\Contracts\RemembersConversations`; every agent must use the `RemembersConversations` trait and store conversations in the database
- Removed the `$history` constructor injection; conversation history is read from the AI SDK store instead of the UI message list
- Session storage is now only a UI cache and holds the active conversation ID

### Changed

- The agent constructor only receives `$user` and `$tenantId`

## [1.1.0] - 2026-11-05

### Added

- Per-panel agent configuration: `agents` can be a flat list or grouped by panel ID with a `'*'` fallback
- Plugin visibility derived from the resolved agents; the `panels` whitelist was removed
- Message actions: copy, edit user messages back into the composer, retract with confirmation, and regenerate the last reply
- Message timestamps rendered in the Filament timezone
- Header status indicator: idle, generating (pulsing) and last-reply-failed states
- Configurable closed-state icon (`ui.toggle_icon`) supporting image paths and full URLs
- In-field clear button
- Database persistence through `laravel/ai`'s `RemembersConversations`; the dialog reads and deletes `agent_conversations` / `agent_conversation_messages` rows
- `timeout` and `debug` configuration keys
- English and Simplified Chinese documentation (`README.md` / `README.en.md`)

### Changed

- Streaming replies no longer show the thinking animation after the first chunk
- Agent isolation now uses the agent config key instead of the class name, so two keys pointing at the same class keep independent conversations
- `agentClass` is validated against the current panel configuration before a reply is requested
- `continueOrStart()` failures are handled by the same error path as streaming failures

### Fixed

- Clearing the conversation now resets the status indicator
- Fatal execution-time errors are avoided by raising the PHP limit for streaming requests

## [1.0.0] - 2026-11-04

### Added

- Initial release: floating dialog, streaming replies, multiple agents, session isolation, dark mode, responsive layout and translations.
