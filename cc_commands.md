# Claude Code Commands & Features Reference

## Built-in Slash Commands

### Session Management
- `/clear` - Clear conversation history
- `/compact [instructions]` - Compact conversation with optional focus instructions
- `/login` - Switch Anthropic accounts
- `/logout` - Sign out from Anthropic account
- `/status` - View account and system statuses

### Configuration & Setup
- `/config` - View/modify configuration
- `/doctor` - Check health of Claude Code installation
- `/init` - Initialize project with CLAUDE.md guide
- `/terminal-setup` - Install Shift+Enter key binding (iTerm2 and VSCode only)
- `/permissions` - View or update permissions

### Project & Code Management
- `/add-dir` - Add additional working directories
- `/memory` - Edit CLAUDE.md memory files
- `/model` - Select or change AI model
- `/review` - Request code review
- `/pr_comments` - View pull request comments

### Utilities
- `/bug` - Report bugs by sending conversation to Anthropic
- `/cost` - Show token usage statistics
- `/help` - Get usage help
- `/vim` - Enter vim mode for alternating insert and command modes

### MCP (Model Context Protocol)
- `/mcp` - Manage MCP server connections and OAuth authentication

## CLI Commands

### Basic Usage
```bash
claude                          # Start interactive REPL
claude "explain this project"   # Start with initial query
```

### Advanced Usage
```bash
claude -p "query"               # Query via SDK and exit
claude -c                       # Continue most recent conversation
claude -c -p "Check for type errors"  # Continue with new query
claude -r "<session-id>" "query"    # Resume specific session
```

### Maintenance
```bash
claude update                   # Update to latest version
claude mcp                      # Configure MCP servers
```

### CLI Flags
- `--add-dir` - Add working directories
- `--model` - Set specific model (e.g., `sonnet`, `opus`)
- `--verbose` - Enable detailed logging
- `--output-format` - Specify response format (text, json, stream-json)
- `--continue` - Load most recent conversation
- `--permission-mode` - Set specific permission mode

## Interactive Mode Features

### Keyboard Shortcuts
- `Ctrl+C` - Cancel current input or generation
- `Ctrl+D` - Exit Claude Code session
- `Ctrl+L` - Clear terminal screen
- `Up/Down arrows` - Navigate command history
- `Esc` + `Esc` - Edit previous message
- `Ctrl+R` - Reverse search command history

### Multiline Input Methods
- `\` + `Enter` - Quick escape (works in all terminals)
- `Option+Enter` - Default on macOS
- `Shift+Enter` - After `/terminal-setup`

### Quick Commands
- `#` at start - Memory shortcut to add to CLAUDE.md
- `/` at start - Invoke slash command
- `@` symbol - Reference files (e.g., "@filename.js")

### Vim Mode
- Enabled with `/vim` command
- Supports navigation: `h/j/k/l` for movement
- Mode switching between NORMAL and INSERT modes

## Custom Slash Commands

### Creating Custom Commands
- **Project-specific**: Store in `.claude/commands/` directory
- **Personal**: Store in `~/.claude/commands/` directory
- **Format**: Markdown files with `$ARGUMENTS` placeholder
- **Features**: Support bash command execution and file references
- **Namespacing**: Use directory structures for organization

### Example Custom Command
```markdown
# Custom Command: /deploy
Deploy the application to staging environment

```bash
npm run build
docker build -t myapp .
docker push myapp:latest
```

Arguments: $ARGUMENTS
```

## MCP Slash Commands

### Format
```bash
/mcp__<server-name>__<prompt-name> [arguments]
```

### Features
- Dynamically discovered from connected MCP servers
- Automatically available when MCP server is active
- Follow consistent naming convention with double underscores

## Common Workflows

### Understanding New Codebases
1. Navigate to project root
2. Start Claude Code
3. Ask for high-level overview: "Explain this project's architecture"
4. Dive into specific components: "@src/components/Header.vue explain this component"

### Code Interaction Techniques
- **File References**: Use `@filename` to reference specific files
- **Pipe Data**: `cat file.js | claude -p "explain this code"`
- **Output Formats**: Control with `--output-format` flag
- **Extended Thinking**: Ask for detailed analysis on complex problems

### Productivity Tips
- **Resume Sessions**: Use `claude -c` to continue previous conversations
- **Parallel Sessions**: Run multiple Claude instances using Git worktrees
- **Custom Commands**: Create project-specific slash commands for common tasks
- **Verification**: Add Claude to your testing/verification processes

## Best Practices

### Query Strategies
1. Start with broad questions, then narrow down
2. Use domain-specific language relevant to your project
3. Break complex tasks into incremental steps
4. Customize prompts for specific project needs

### Session Management
- Use `/clear` regularly to maintain context freshness
- Use `/compact` to summarize long conversations
- Resume conversations with `/continue` for context continuity
- Use meaningful session IDs for easy identification

### Configuration
- Initialize projects with `/init` for consistent setup
- Use `/memory` to maintain project-specific context
- Configure MCP servers with `/mcp` for enhanced functionality
- Set appropriate permissions with `/permissions`

## Advanced Features

### Image Analysis
- Claude Code can analyze images and screenshots
- Useful for UI/UX review and documentation
- Supports various image formats

### Context Preservation
- Conversations maintain context across sessions
- Project-specific memory through CLAUDE.md
- Command history stored per working directory

### Flexible Output
- Text, JSON, and streaming JSON formats
- Customizable response formats for different use cases
- Pipeline-friendly output options

### Integration Capabilities
- Git integration for pull request reviews
- MCP server connections for extended functionality
- Custom command creation for workflow automation