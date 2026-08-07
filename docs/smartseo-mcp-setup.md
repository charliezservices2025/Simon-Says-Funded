# SmartSEO MCP server

This repo ships a project-scoped MCP server definition in [`.mcp.json`](../.mcp.json) so
anyone working in it gets the SmartSEO tools in Claude Code.

| | |
|---|---|
| Server name | `smartseo` |
| Transport | Streamable HTTP |
| Endpoint | `https://app-smartseo-prod.azurewebsites.net/mcp` |
| Auth | `Authorization: Bearer <API key>` |

The API key is **not** stored in the repo. `.mcp.json` references it as
`${SMARTSEO_API_KEY}`, which Claude Code expands from the environment when it
connects to the server. If the variable is unset, the server fails to connect —
that is the intended behaviour, not a bug.

## Setup

Pick one of the following.

### Option A — shell environment (recommended)

Add the key to your shell profile so every Claude Code session picks it up:

```bash
echo 'export SMARTSEO_API_KEY="sseo_live_..."' >> ~/.zshrc   # or ~/.bashrc
source ~/.zshrc
```

Then start Claude Code from that shell.

### Option B — local settings file

Create `.claude/settings.local.json` in the repo root. It is git-ignored, so the
key stays on your machine:

```json
{
  "env": {
    "SMARTSEO_API_KEY": "sseo_live_..."
  }
}
```

## First run

The first time Claude Code sees a new `.mcp.json`, it asks you to approve the
project's MCP servers. `.claude/settings.json` pre-approves `smartseo` via
`enabledMcpjsonServers`, so it should connect without a prompt.

Verify with:

```
/mcp
```

`smartseo` should be listed as connected, and its tools show up as
`mcp__smartseo__*`.

## Troubleshooting

- **`smartseo` missing from `/mcp`** — Claude Code was started from a directory
  other than the repo root. Project-scoped `.mcp.json` only loads for the
  project you opened.
- **Connects but every call returns 401** — `SMARTSEO_API_KEY` is unset or stale.
  Confirm with `echo $SMARTSEO_API_KEY` in the same shell that launched Claude
  Code; the literal string `${SMARTSEO_API_KEY}` reaching the server means the
  variable was empty at connect time.
- **Connection refused / TLS errors behind a corporate proxy** — the endpoint is
  plain HTTPS on Azure App Service; allow-list
  `app-smartseo-prod.azurewebsites.net` in your egress policy.
- **Key rotation** — replace the value in your shell profile or
  `.claude/settings.local.json` and restart Claude Code. No repo change needed.

## Note on the key format

Keys are prefixed `sseo_live_`. Treat them as production credentials: they are
bearer tokens, so anyone holding one can call the API as you. Never paste one
into a file that is tracked by git, an issue, or a pull request. If a key is
exposed, rotate it in the SmartSEO dashboard.
