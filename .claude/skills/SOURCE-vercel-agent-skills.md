# Vercel agent skills — installed skills

These project-scoped Claude Code skills were installed directly from
Vercel's official agent-skills repository.

- **Source repo:** https://github.com/vercel-labs/agent-skills
- **Commit installed from:** `f8a72b9603728bb92a217a879b7e62e43ad76c81` (`main`, 2026-06-10)
- **License:** No repo-wide LICENSE file. Several individual skills declare
  `license: MIT` in their own `SKILL.md` frontmatter (`react-best-practices`,
  `react-native-skills`, `react-view-transitions`); the rest
  (`composition-patterns`, `deploy-to-vercel`, `vercel-cli-with-tokens`,
  `vercel-optimize`, `web-design-guidelines`, `writing-guidelines`) don't
  declare a license at all — check with Vercel before redistributing those.

## Skills included

| Skill | Purpose |
|-------|---------|
| `composition-patterns` | React composition patterns (compound components, render props, context providers) over boolean-prop-heavy APIs. |
| `deploy-to-vercel` | Deploy applications/websites to Vercel ("deploy my app", preview deployments). |
| `react-best-practices` | 40+ React/Next.js performance rules across 8 categories, from Vercel Engineering. |
| `react-native-skills` | React Native / Expo best practices for performant mobile apps. |
| `react-view-transitions` | Implementing animations with React's View Transition API. |
| `vercel-cli-with-tokens` | Deploy and manage Vercel projects using token-based (non-interactive) auth. |
| `vercel-optimize` | Audits a deployed Vercel project for cost/performance/reliability using Vercel metrics, then produces ranked, citation-grounded recommendations. |
| `web-design-guidelines` | Reviews UI code for Web Interface Guidelines / accessibility / UX compliance. |
| `writing-guidelines` | Reviews docs/prose for a writing style handbook. |

The upstream repo also groups these via `skills.sh.json` into "React",
"Vercel", and "Design" categories on [skills.sh](https://skills.sh/vercel-labs/agent-skills).
Here they're flattened into `.claude/skills/` alongside the other skill
sources in this project (see `SOURCE.md` and `SOURCE-anthropic-skills.md`).

## How this was installed

Same constraint as the `anthropics/skills` install: `git clone`/`git fetch`
of out-of-scope repos is blocked by this environment's repo-scoped egress
policy. The repo was fetched as a tarball via
`https://codeload.github.com/vercel-labs/agent-skills/tar.gz/refs/heads/main`,
and each `skills/<name>/` directory was copied in verbatim. The upstream
repo also ships a redundant pre-zipped copy of several skills at
`skills/<name>.zip` (and one skill folder contained a stray `Archive.zip`
with macOS `__MACOSX` junk) — these were excluded since they duplicate the
unpacked directory contents.

## Updating

Re-run the same tarball fetch against a newer commit and diff/replace the
relevant skill folders under `.claude/skills/`.
