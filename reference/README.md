# Reference

Material kept with the project for reference. Nothing here is loaded by AGAPAY, its tests or its build.

## design/

- `specs/2026-09-28-agapay-design.md`: the system design. It covers the routes and Figma frames, the data model, the paper's rules and how each one is implemented, and the build phases.
- `plans/`: the implementation plan for each build phase (1–9), with the tests that prove each feature.

## ai-tooling/

Configuration for the AI coding assistant used during development: Claude Code, set up through the Laravel Boost package (`laravel/boost`).

- `CLAUDE.md`, `AGENTS.md`: project guidelines for the assistant (Laravel conventions, testing rules).
- `boost.json`, `.mcp.json`: Laravel Boost settings.
- `claude-skills/`: Laravel Boost's guideline files for the assistant (Laravel best practices, testing, Tailwind).

To use the assistant on this project again, copy `CLAUDE.md`, `AGENTS.md`, `boost.json` and `.mcp.json` back to the project root, and `claude-skills/` back to `.claude/skills/`.
