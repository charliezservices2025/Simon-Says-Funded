# bencium-marketplace — installed skills

These project-scoped Claude Code skills were installed directly from
bencium.io's Claude Code plugin marketplace.

- **Source repo:** https://github.com/bencium/bencium-marketplace
- **Commit installed from:** `c6f5a718d43ba5a5d540bf74efbd4dba81400c7` (`main`, 2026-06-03)
- **License:** No LICENSE file in the source repo (GitHub reports no
  detected license). The repo's own README explicitly documents this
  install path though: *"These skills work as SKILL.md files in the Claude
  Cowork app. Copy the `skills/` directory from any plugin into your
  project's `.claude/skills/` folder."* Treat as all-rights-reserved beyond
  that documented use until confirmed otherwise with bencium.io.

## Skills included

| Skill | Purpose |
|-------|---------|
| `adaptive-communication` | Detects ambiguous intent / hedging / open-ended framing and adapts response style accordingly. |
| `bencium-aeo` | Answer Engine Optimization — content tuned for AI search citations (ChatGPT, Claude, Gemini, AI Overviews). |
| `bencium-code-conventions` | Code style, tech stack, and workflow conventions for React/Next.js/TypeScript, Tailwind, Supabase projects. |
| `bencium-controlled-ux-designer` | UI/UX design guidance with an always-ask-first protocol for visual decisions. |
| `bencium-impact-designer` | Production-grade frontend interfaces with bold, non-generic design (based on Anthropic's frontend-design skill). |
| `bencium-innovative-ux-designer` | Distinctive, production-grade frontend interfaces avoiding generic AI aesthetics. |
| `design-audit` | Systematic visual UI/UX audits producing phased, implementation-ready design plans. |
| `human-architect-mindset` | Systematic architectural thinking — domain modeling, systems thinking, constraint navigation. |
| `insurgent-campaign` | Grassroots-first campaign design for under-resourced startups/NGOs/movements. |
| `negentropy-lens` | Decision-support framework evaluating systems through entropy vs. negentropy. |
| `relationship-design` | Agentic UX design for AI-first interfaces centered on memory and trust evolution. |
| `renaissance-architecture` | Software architecture / UI-UX principles emphasizing first-principles thinking over derivative work. |
| `typography` | Professional typography rules (quotes, dashes, spacing, hierarchy) enforced on generated HTML/CSS/React. |
| `vanity-engineering-review` | Reviews code/architecture/PRs for "vanity engineering" built for ego rather than user value. |

One plugin from the marketplace, **`bencium-harness`**, was *not* included —
its `source` in `.claude-plugin/marketplace.json` points to a separate repo
(`bencium/bencium-harness`), not this one, so it was out of scope for this
URL.

## How this was installed

Same constraint as the other skill installs in this project: `git
clone`/`git fetch` of out-of-scope repos is blocked by this environment's
repo-scoped egress policy. The repo was fetched as a tarball via
`https://codeload.github.com/bencium/bencium-marketplace/tar.gz/refs/heads/main`.
Each plugin in this repo wraps its skill in `<plugin>/skills/<plugin>/`
alongside a `.claude-plugin/plugin.json` and `README.md` — only the inner
`skills/<name>/` directory was copied into `.claude/skills/`, matching the
flattened layout used for the other skill sources in this project (see
`SOURCE.md`, `SOURCE-anthropic-skills.md`, `SOURCE-vercel-agent-skills.md`).

## Updating

Re-run the same tarball fetch against a newer commit and diff/replace the
relevant skill folders under `.claude/skills/`.
