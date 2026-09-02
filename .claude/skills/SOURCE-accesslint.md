# AccessLint — installed skills

These project-scoped Claude Code skills were installed directly from
AccessLint's Claude plugin repository.

- **Source repo:** https://github.com/AccessLint/skills
- **Commit installed from:** `ceb3fa80fc8be8d8959f5b3eb812ac8cc33a5a59` (`main`, 2026-06-04)
- **Plugin:** `accesslint` v0.8.0 (`.claude-plugin/marketplace.json`), source folder `plugins/accesslint`
- **License:** No LICENSE file in the source repo (GitHub reports no
  detected license) and no `license:` field in any `SKILL.md`. Treat as
  all-rights-reserved until confirmed otherwise with AccessLint.

## Skills included

| Skill | Purpose |
|-------|---------|
| `audit` | WCAG 2.2 audit, two modes: report-only sweep, or audit→edit→verify fix loop. |
| `scan` | Full live-DOM audit of a page/target, locates each violation by selector and `file:line`. |
| `diff` | Diffs a live page's a11y violations against a baseline (uncommitted changes or a branch) — reports only what changed. |

Upstream these are namespaced as `accesslint:audit`, `accesslint:scan`,
`accesslint:diff` when installed as a marketplace plugin. Here they're
flattened into `.claude/skills/` by their own folder names, like the other
skill sources in this project.

## Important: requires the `@accesslint/mcp` MCP server

Unlike the other skill sources installed in this project, these skills are
**not self-contained** — each `SKILL.md`'s `allowed-tools` references MCP
tools (`mcp__accesslint__audit_html`, `mcp__accesslint__audit_live`,
`mcp__accesslint__explain_rule`, `mcp__accesslint__list_rules`) provided by
a separate MCP server, not by the skill files themselves. **That server was
not installed or configured here** — doing so means running
`npx @accesslint/mcp@latest` (which AccessLint's live-DOM auditing also uses
to auto-launch Chrome via CDP), a runtime/network change beyond copying
skill files. To actually use `audit`/`scan`/`diff`, add the MCP server
first, e.g. via Claude Code:

```bash
claude mcp add accesslint npx -- -y @accesslint/mcp@latest
```

See the upstream `plugins/accesslint/.mcp.json` and README for details and
alternatives (`chrome-devtools-mcp`, `playwright-mcp`, `puppeteer-mcp`, or
the static-only `@accesslint/cli`).

## How this was installed

Same constraint as the other skill installs in this project: `git
clone`/`git fetch` of out-of-scope repos is blocked by this environment's
repo-scoped egress policy. The repo was fetched as a tarball via
`https://codeload.github.com/AccessLint/skills/tar.gz/refs/heads/main`, and
the three skill directories under `plugins/accesslint/skills/` were copied
in verbatim (the plugin's `.mcp.json` was left in place upstream — not
copied here, see note above).

## Updating

Re-run the same tarball fetch against a newer commit and diff/replace
`audit/`, `scan/`, `diff/` under `.claude/skills/`.
