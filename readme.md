# Content Abilities

A WordPress plugin that registers content management abilities via the Abilities API (6.9+), exposable to AI agents through the [MCP Adapter](https://github.com/WordPress/mcp-adapter).

## Abilities

| ID | Description |
|---|---|
| `content/create-post` | Create a post with title, content, excerpt, status, categories, and tags |
| `content/update-post` | Update an existing post — only provided fields are modified |
| `content/get-post` | Retrieve a single post by ID |
| `content/find-posts` | Search and filter posts by keyword, type, and status |

All abilities are scoped to public post types and enforce WordPress capability checks (create, edit, publish, read).

## Requirements

- WordPress 6.9+
- [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin

## Installation

1. Clone or copy this plugin into `wp-content/plugins/content-abilities/`
2. Activate both MCP Adapter and Content Abilities in wp-admin

## Connecting Claude Code

Add to your Claude Code MCP config (`~/.claude.json`):

```json
{
  "mcpServers": {
    "wordpress": {
      "type": "stdio",
      "command": "wp",
      "args": [
        "--path=/path/to/your/wordpress",
        "mcp-adapter", "serve",
        "--server=mcp-adapter-default-server",
        "--user=admin"
      ]
    }
  }
}
```

For remote sites, use the HTTP transport via `@automattic/mcp-wordpress-remote` with application passwords.

## License

GPL-2.0-or-later
