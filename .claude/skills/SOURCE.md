# UI/UX Pro Max — installed skills

These project-scoped Claude Code skills were installed from the
**ui-ux-pro-max** plugin.

- **Source repo:** https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
- **Plugin version:** 2.6.2 (`.claude-plugin/plugin.json`)
- **Branch installed from:** `main`
- **Homepage:** https://uupm.cc
- **License:** MIT

## Skills included

| Skill | Purpose |
|-------|---------|
| `ui-ux-pro-max` | Core UI/UX design intelligence: styles, color palettes, font pairings, UX guidelines, charts across many stacks. |
| `design` | Logos, corporate identity, mockups, slides, banners, icons, social images. |
| `design-system` | Design tokens (primitive → semantic → component), component specs, slide generation. |
| `ui-styling` | shadcn/ui + Tailwind UIs, accessible components, canvas-based visuals. |
| `brand` | Brand voice, visual identity, messaging frameworks, asset management. |
| `banner-design` | Banners for social, ads, web heroes, and print. |
| `slides` | Strategic HTML presentations with Chart.js. |

## How this was installed

The upstream `impeccable` installer and the Claude marketplace flow both
require network hosts that this environment's egress policy blocks, so the
skills were copied directly from the upstream repo's `.claude/skills/`
tree (the exact content the plugin ships) at project scope.

## Updating

When a newer release is published, refresh from the upstream repo's
`.claude/skills/` directory, or use the maintained CLI from a machine with
open network access:

```bash
npm install -g ui-ux-pro-max-cli
uipro init --ai claude        # or: uipro update --ai claude
```
