# Anthropic example & document skills — installed skills

These project-scoped Claude Code skills were installed directly from
Anthropic's official skills repository.

- **Source repo:** https://github.com/anthropics/skills
- **Commit installed from:** `35414756ca55738e050562e272a6bbc6273aa926` (`main`, 2026-06-27)
- **License:** Most skills are Apache 2.0. The document skills (`docx`,
  `pdf`, `pptx`, `xlsx`) and `claude-api` are source-available (not open
  source) under Anthropic's terms — see each skill's own `LICENSE.txt`.

## Skills included

| Skill | Purpose |
|-------|---------|
| `algorithmic-art` | Generative/algorithmic art with p5.js, seeded randomness, flow fields, particle systems. |
| `brand-guidelines` | Applies Anthropic's official brand colors and typography to artifacts. |
| `canvas-design` | Creates visual art (.png/.pdf) using design philosophy and canvas tooling. |
| `claude-api` | Reference for the Claude API / Anthropic SDK — models, pricing, streaming, tool use, MCP, agents. |
| `doc-coauthoring` | Structured workflow for co-authoring docs, proposals, specs, and decision docs. |
| `docx` | Create, read, and edit Word (.docx) documents. |
| `frontend-design` | Guidance for distinctive, intentional visual design when building UI. |
| `internal-comms` | Templates and guidance for internal communications (status reports, updates, FAQs, incident reports). |
| `mcp-builder` | Guide for building high-quality MCP servers (Python FastMCP or Node/TypeScript SDK). |
| `pdf` | Read, create, merge/split, watermark, fill forms, and OCR PDF files. |
| `pptx` | Create, read, and edit PowerPoint (.pptx) presentations. |
| `skill-creator` | Create, edit, and evaluate Claude Code skills. |
| `slack-gif-creator` | Build animated GIFs optimized for Slack. |
| `theme-factory` | Apply or generate themes (colors/fonts) for slides, docs, and HTML artifacts. |
| `web-artifacts-builder` | Build elaborate multi-component HTML artifacts (React, Tailwind, shadcn/ui). |
| `webapp-testing` | Test local web apps with Playwright (screenshots, browser logs, UI verification). |
| `xlsx` | Create, read, and edit spreadsheet (.xlsx/.xlsm/.csv/.tsv) files. |

Note: this repo's [`.claude-plugin/marketplace.json`](https://github.com/anthropics/skills/blob/main/.claude-plugin/marketplace.json)
groups these into the `document-skills` (`xlsx`, `docx`, `pptx`, `pdf`) and
`example-skills` (everything else except `claude-api`) plugins; `claude-api`
ships as its own plugin. Here they're flattened into `.claude/skills/`
alongside the existing design skills (see `SOURCE.md`) for project scope.

## How this was installed

`git clone`/`git fetch` of `github.com/anthropics/skills` is blocked by this
environment's repo-scoped egress policy (only the active session's source
repo is reachable over git). Plain HTTPS requests are not scoped the same
way, so the repo was instead fetched as a tarball via
`https://codeload.github.com/anthropics/skills/tar.gz/refs/heads/main` and
the `skills/` subfolders were copied in verbatim, license files included.

## Updating

Re-run the same tarball fetch against a newer commit and diff/replace the
relevant skill folders under `.claude/skills/`, or — from a machine with
unrestricted network access — use `/plugin marketplace add anthropics/skills`
followed by `/plugin install document-skills@anthropic-agent-skills` and
`/plugin install example-skills@anthropic-agent-skills` in Claude Code.
