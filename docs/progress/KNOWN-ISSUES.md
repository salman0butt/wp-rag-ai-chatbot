# Known Issues

## KI-001 — Local GitHub DNS unavailable in current chat runtime

Status: OPEN / ENVIRONMENT

`git clone https://github.com/salman0butt/wp-rag-ai-chatbot.git` failed because the container could not resolve github.com. The connected GitHub integration is therefore being used for branch-isolated documentation work. This does not indicate a repository fault. Native Superpowers worktree setup must be used when execution moves to a runtime with repository network access.

## KI-002 — Baseline tests do not exist yet

Status: EXPECTED

The repository began empty. No production project/toolchain/tests exist before M01, so there is no executable baseline test command yet.
