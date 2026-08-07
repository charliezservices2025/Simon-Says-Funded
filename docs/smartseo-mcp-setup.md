# SmartSEO MCP server

This repo ships a project-scoped MCP server definition in [`.mcp.json`](../.mcp.json) so
anyone working in it gets the SmartSEO tools in Claude Code.

| | |
|---|---|
| Server name | `smartseo` |
| Transport | Streamable HTTP |
| Endpoint | `https://app-smartseo-prod.azurewebsites.net/mcp` |
| Auth | `Authorization: Bearer <API key>` |

## Setup

None. The API key is committed inline in `.mcp.json`, so the server works on
clone with no environment variables to export and no local files to create.

## Verifying

Open Claude Code at the repo root and run:

```
/mcp
```

`smartseo` should be listed as connected, and its tools appear as
`mcp__smartseo__*`. `.claude/settings.json` pre-approves the server via
`enabledMcpjsonServers`, so the project-MCP trust prompt should not appear.

## Rotating the key

The key lives in exactly one place — the `Authorization` header in `.mcp.json`.
To rotate:

1. Issue a new key in the SmartSEO dashboard.
2. Replace the `sseo_live_…` value in `.mcp.json` and commit.
3. Revoke the old key in the dashboard.

Step 3 is the one that matters. Because this repository is public, every key
committed here is readable by anyone and remains in git history after it is
replaced — editing the file does not unpublish the old value. Revoking is the
only thing that actually retires a key.

## Troubleshooting

- **`smartseo` missing from `/mcp`** — Claude Code was started from a directory
  other than the repo root. Project-scoped `.mcp.json` only loads for the
  project you opened.
- **Every call returns 401** — the committed key was revoked or rotated. Follow
  the rotation steps above.
- **Connection refused / TLS errors behind a corporate proxy** — the endpoint is
  plain HTTPS on Azure App Service; allow-list
  `app-smartseo-prod.azurewebsites.net` in your egress policy.
