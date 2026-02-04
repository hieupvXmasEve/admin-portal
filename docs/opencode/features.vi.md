# Tính năng của Oh-My-OpenCode

Bản dịch tiếng Việt của tài liệu gốc: https://github.com/code-yeongyu/oh-my-opencode/blob/dev/docs/features.md

---

## Agents: Đội AI của bạn

Oh-My-OpenCode cung cấp 11 tác nhân (agent) AI chuyên biệt. Mỗi tác nhân có chuyên môn riêng, mô hình tối ưu và quyền hạn công cụ khác nhau.

### Tác nhân cốt lõi

| Tác nhân              | Mô hình                      | Mục đích                                                                                                                                                                                                                                                                                                                                                                                                                  |
| --------------------- | ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Sisyphus**          | `anthropic/claude-opus-4-5`  | **Bộ điều phối mặc định.** Lập kế hoạch, ủy quyền và thực thi các tác vụ phức tạp bằng các tác nhân con chuyên biệt với mức độ chạy song song mạnh. Quy trình dựa trên todo với chế độ suy nghĩ mở rộng (ngân sách 32k). Fallback: kimi-k2.5 → glm-4.7 → gpt-5.2-codex → gemini-3-pro.                                                                                                                                    |
| **Hephaestus**        | `openai/gpt-5.2-codex`       | **Người thợ lành nghề đích thực.** Tác nhân làm việc tự động theo deep mode (lấy cảm hứng từ AmpCode). Thực thi theo mục tiêu với nghiên cứu kỹ trước khi hành động. Khám phá các pattern trong codebase, hoàn thành tác vụ end-to-end mà không dừng sớm. Đặt tên theo vị thần thợ rèn và nghệ thuật chế tác trong thần thoại Hy Lạp. Yêu cầu gpt-5.2-codex (không có fallback - chỉ kích hoạt khi mô hình này khả dụng). |
| **oracle**            | `openai/gpt-5.2`             | Quyết định kiến trúc, review code, debug. Tư vấn chỉ-đọc - lập luận logic và phân tích sâu. Lấy cảm hứng từ AmpCode.                                                                                                                                                                                                                                                                                                      |
| **librarian**         | `zai-coding-plan/glm-4.7`    | Phân tích nhiều repo, tra cứu tài liệu, tìm ví dụ triển khai OSS. Hiểu sâu codebase dựa trên bằng chứng. Fallback: glm-4.7-free → claude-sonnet-4-5.                                                                                                                                                                                                                                                                      |
| **explore**           | `anthropic/claude-haiku-4-5` | Khám phá codebase nhanh và contextual grep. Fallback: gpt-5-mini → gpt-5-nano.                                                                                                                                                                                                                                                                                                                                            |
| **multimodal-looker** | `google/gemini-3-flash`      | Chuyên gia nội dung hình ảnh. Phân tích PDF, ảnh, sơ đồ để trích xuất thông tin. Fallback: gpt-5.2 → glm-4.6v → kimi-k2.5 → claude-haiku-4-5 → gpt-5-nano.                                                                                                                                                                                                                                                                |

### Tác nhân lập kế hoạch

| Tác nhân       | Mô hình                     | Mục đích                                                                                                                                                        |
| -------------- | --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Prometheus** | `anthropic/claude-opus-4-5` | Nhà lập kế hoạch chiến lược với chế độ phỏng vấn. Tạo kế hoạch làm việc chi tiết thông qua hỏi đáp lặp. Fallback: kimi-k2.5 → gpt-5.2 → gemini-3-pro.           |
| **Metis**      | `anthropic/claude-opus-4-5` | Cố vấn kế hoạch - phân tích trước khi lập kế hoạch. Nhận diện ý đồ ẩn, điểm mơ hồ và các điểm dễ thất bại của AI. Fallback: kimi-k2.5 → gpt-5.2 → gemini-3-pro. |
| **Momus**      | `openai/gpt-5.2`            | Người review kế hoạch - kiểm tra kế hoạch theo các tiêu chí rõ ràng, có thể kiểm chứng và đầy đủ. Fallback: gpt-5.2 → claude-opus-4-5 → gemini-3-pro.           |

### Gọi tác nhân

Tác nhân chính sẽ tự gọi các tác nhân này, nhưng bạn có thể gọi trực tiếp:

```
Ask @oracle to review this design and propose an architecture
Ask @librarian how this is implemented - why does the behavior keep changing?
Ask @explore for the policy on this feature
```

### Hạn chế công cụ

| Tác nhân          | Hạn chế                                       |
| ----------------- | --------------------------------------------- |
| oracle            | Chỉ-đọc: không thể write, edit, hoặc delegate |
| librarian         | Không thể write, edit, hoặc delegate          |
| explore           | Không thể write, edit, hoặc delegate          |
| multimodal-looker | Chỉ theo allowlist: read, glob, grep          |

### Tác nhân nền (Background Agents)

Chạy tác nhân trong nền và tiếp tục làm việc:

- Để GPT debug trong khi Claude thử các hướng tiếp cận khác
- Gemini viết frontend trong khi Claude xử lý backend
- Bật tìm kiếm song song quy mô lớn, tiếp tục implement, dùng kết quả khi cần

```
# Launch in background
delegate_task(subagent_type="explore", load_skills=[], prompt="Find auth implementations", run_in_background=true)

# Continue working...
# System notifies on completion

# Retrieve results when needed
background_output(task_id="bg_abc123")
```

#### Visual Multi-Agent với Tmux

Bật `tmux.enabled` để xem tác nhân nền ở các pane tmux riêng:

```json
{
    "tmux": {
        "enabled": true,
        "layout": "main-vertical"
    }
}
```

Khi chạy trong tmux:

- Tác nhân nền spawn sang pane mới
- Theo dõi nhiều tác nhân làm việc theo thời gian thực
- Mỗi pane hiển thị output trực tiếp
- Tự cleanup khi tác nhân hoàn tất

Xem [Tmux Integration](configurations.md#tmux-integration) để biết đầy đủ tuỳ chọn cấu hình.

Tuỳ biến mô hình, prompt và quyền hạn trong `oh-my-opencode.json`. Xem [Configuration](configurations.md#agents).

---

## Skills: Kiến thức chuyên biệt

Skills cung cấp quy trình chuyên biệt với MCP server nhúng sẵn và hướng dẫn chi tiết.

### Skills tích hợp sẵn

| Skill              | Kích hoạt                               | Mô tả                                                                                                                                                          |
| ------------------ | --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **playwright**     | Tác vụ trình duyệt, testing, screenshot | Tự động hoá trình duyệt qua Playwright MCP. BẮT BUỘC dùng cho mọi tác vụ liên quan trình duyệt - xác minh, duyệt web, web scraping, testing, screenshots.      |
| **frontend-ui-ux** | UI/UX, styling                          | Persona designer-turned-developer. Tạo UI/UX ấn tượng kể cả khi không có thiết kế mẫu. Nhấn mạnh hướng thẩm mỹ rõ rệt, typography khác biệt, bảng màu đồng bộ. |
| **git-master**     | commit, rebase, squash, blame           | BẮT BUỘC dùng cho mọi tác vụ git. Commit nguyên tử với tự động tách, workflow rebase/squash, tìm lịch sử (blame, bisect, log -S).                              |

### Skill: Tự động hoá trình duyệt (playwright / agent-browser)

**Kích hoạt**: Bất kỳ yêu cầu liên quan trình duyệt

Oh-My-OpenCode cung cấp hai nhà cung cấp tự động hoá trình duyệt, cấu hình qua `browser_automation_engine.provider`:

#### Tuỳ chọn 1: Playwright MCP (mặc định)

Nhà cung cấp mặc định dùng Playwright MCP server:

```yaml
mcp:
    playwright:
        command: npx
        args: ['@playwright/mcp@latest']
```

**Cách dùng**:

```
/playwright Navigate to example.com and take a screenshot
```

#### Tuỳ chọn 2: Agent Browser CLI (Vercel)

Nhà cung cấp thay thế dùng [Vercel's agent-browser CLI](https://github.com/vercel-labs/agent-browser):

```json
{
    "browser_automation_engine": {
        "provider": "agent-browser"
    }
}
```

**Cần cài đặt**:

```bash
bun add -g agent-browser
```

**Cách dùng**:

```
Use agent-browser to navigate to example.com and extract the main heading
```

#### Khả năng (cả hai tuỳ chọn)

- Điều hướng và tương tác trang web
- Chụp ảnh màn hình và xuất PDF
- Điền form và click phần tử
- Chờ các network request
- Lấy dữ liệu (scrape) nội dung

### Skill: frontend-ui-ux

**Kích hoạt**: Tác vụ thiết kế UI, thay đổi giao diện

Một designer-turned-developer tạo giao diện ấn tượng:

- **Quy trình thiết kế**: Mục đích, Tone, Ràng buộc, Điểm khác biệt
- **Hướng thẩm mỹ**: Chọn cực đoan - brutalist, maximalist, retro-futuristic, luxury, playful
- **Typography**: Font khác biệt, tránh generic (Inter, Roboto, Arial)
- **Màu sắc**: Bảng màu đồng bộ với điểm nhấn sắc, tránh purple-on-white AI slop
- **Chuyển động**: Staggered reveals ấn tượng, scroll-triggering, hover states bất ngờ
- **Anti-patterns**: Font chung chung, layout đoán trước được, thiết kế rập khuôn (cookie-cutter)

### Skill: git-master

**Kích hoạt**: commit, rebase, squash, "who wrote", "when was X added"

Ba chuyên môn trong một:

1. **Commit Architect**: Commit nguyên tử, sắp xếp phụ thuộc, phát hiện phong cách
2. **Rebase Surgeon**: Viết lại lịch sử, xử lý conflict, dọn dẹp nhánh
3. **History Archaeologist**: Tìm khi/nơi thay đổi được đưa vào

**Nguyên tắc cốt lõi - mặc định nhiều commit**:

```
3+ files -> MUST be 2+ commits
5+ files -> MUST be 3+ commits
10+ files -> MUST be 5+ commits
```

**Tự động phát hiện phong cách**:

- Phân tích 30 commit gần nhất để phát hiện ngôn ngữ (Korean/English) và kiểu (semantic/plain/short)
- Tự động khớp quy ước commit của repo

**Cách dùng**:

```
/git-master commit these changes
/git-master rebase onto main
/git-master who wrote this authentication code?
```

### Custom Skills

Load custom skills từ:

- `.opencode/skills/*/SKILL.md` (project)
- `~/.config/opencode/skills/*/SKILL.md` (user)
- `.claude/skills/*/SKILL.md` (Claude Code compat)
- `~/.claude/skills/*/SKILL.md` (Claude Code user)

Tắt built-in skills bằng `disabled_skills: ["playwright"]` trong config.

---

## Commands: Slash Workflows

Commands là workflow kích hoạt bằng slash, thực thi các template định sẵn.

### Built-in Commands

| Command         | Mô tả                                                                             |
| --------------- | --------------------------------------------------------------------------------- |
| `/init-deep`    | Khởi tạo hệ AGENTS.md phân cấp                                                    |
| `/ralph-loop`   | Bắt đầu vòng lặp tự-phản-chiếu cho đến khi hoàn thành                             |
| `/ulw-loop`     | Bắt đầu ultrawork loop - tiếp tục với ultrawork mode                              |
| `/cancel-ralph` | Huỷ Ralph Loop đang chạy                                                          |
| `/refactor`     | Refactor thông minh với LSP, AST-grep, phân tích kiến trúc và kiểm chứng theo TDD |
| `/start-work`   | Bắt đầu phiên làm việc Sisyphus từ kế hoạch Prometheus                            |

### Command: /init-deep

**Mục đích**: Tạo các file AGENTS.md phân cấp khắp dự án

**Cách dùng**:

```
/init-deep [--create-new] [--max-depth=N]
```

Tạo các file context theo thư mục mà agents tự động đọc:

```
project/
├── AGENTS.md              # Project-wide context
├── src/
│   ├── AGENTS.md          # src-specific context
│   └── components/
│       └── AGENTS.md      # Component-specific context
```

### Command: /ralph-loop

**Mục đích**: Vòng lặp phát triển tự-phản-chiếu chạy đến khi hoàn thành tác vụ

**Đặt tên theo**: Anthropic's Ralph Wiggum plugin

**Cách dùng**:

```
/ralph-loop "Build a REST API with authentication"
/ralph-loop "Refactor the payment module" --max-iterations=50
```

**Hành vi**:

- Agent làm việc liên tục hướng tới mục tiêu
- Nhận diện `<promise>DONE</promise>` để biết khi nào hoàn tất
- Tự tiếp tục nếu agent dừng mà chưa hoàn thành
- Kết thúc khi: phát hiện hoàn thành, đạt max iterations (mặc định 100), hoặc `/cancel-ralph`

**Cấu hình**: `{ "ralph_loop": { "enabled": true, "default_max_iterations": 100 } }`

### Command: /ulw-loop

**Mục đích**: Giống ralph-loop nhưng bật ultrawork mode

Mọi thứ chạy ở mức tối đa - chạy song song, tác nhân nền, khám phá mạnh.

### Command: /refactor

**Mục đích**: Refactor thông minh với toolchain đầy đủ

**Cách dùng**:

```
/refactor <target> [--scope=<file|module|project>] [--strategy=<safe|aggressive>]
```

**Tính năng**:

- Rename và điều hướng bằng LSP
- AST-grep để match pattern
- Phân tích kiến trúc trước khi thay đổi
- Kiểm chứng theo TDD sau khi thay đổi
- Tạo codemap

### Command: /start-work

**Mục đích**: Bắt đầu thực thi từ kế hoạch do Prometheus tạo

**Cách dùng**:

```
/start-work [plan-name]
```

Dùng atlas agent để thực thi các task theo kế hoạch một cách hệ thống.

### Custom Commands

Load custom commands từ:

- `.opencode/command/*.md` (project)
- `~/.config/opencode/command/*.md` (user)
- `.claude/commands/*.md` (Claude Code compat)
- `~/.claude/commands/*.md` (Claude Code user)

---

## Hooks: Tự động hoá vòng đời

Hooks chặn và điều chỉnh hành vi ở các điểm then chốt trong vòng đời của agent.

### Hook Events

| Event                | Khi nào                | Có thể                                    |
| -------------------- | ---------------------- | ----------------------------------------- |
| **PreToolUse**       | Trước khi chạy tool    | Chặn, sửa input, inject context           |
| **PostToolUse**      | Sau khi chạy tool      | Thêm cảnh báo, sửa output, inject message |
| **UserPromptSubmit** | Khi user submit prompt | Chặn, inject message, transform prompt    |
| **Stop**             | Khi session rảnh       | Inject follow-up prompts                  |

### Built-in Hooks

#### Context & Injection

| Hook                            | Event       | Mô tả                                                                                                                                                                                   |
| ------------------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **directory-agents-injector**   | PostToolUse | Tự động inject AGENTS.md khi đọc file. Walk từ file lên project root, thu thập tất cả AGENTS.md. **Deprecated for OpenCode 1.1.37+** - Auto-disabled khi có native AGENTS.md injection. |
| **directory-readme-injector**   | PostToolUse | Tự động inject README.md cho context thư mục.                                                                                                                                           |
| **rules-injector**              | PostToolUse | Inject rules từ `.claude/rules/` khi match điều kiện. Hỗ trợ globs và alwaysApply.                                                                                                      |
| **compaction-context-injector** | Stop        | Giữ lại context quan trọng khi session bị compaction.                                                                                                                                   |

#### Productivity & Control

| Hook                   | Event            | Mô tả                                                                                                                                                     |
| ---------------------- | ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **keyword-detector**   | UserPromptSubmit | Phát hiện từ khoá và kích hoạt mode: `ultrawork`/`ulw` (tối đa hiệu năng), `search`/`find` (khám phá song song), `analyze`/`investigate` (phân tích sâu). |
| **think-mode**         | UserPromptSubmit | Tự phát hiện nhu cầu extended thinking. Bắt các cụm "think deeply", "ultrathink" và điều chỉnh cài đặt mô hình.                                           |
| **ralph-loop**         | Stop             | Quản lý việc tiếp tục vòng lặp tự-phản-chiếu.                                                                                                             |
| **start-work**         | PostToolUse      | Xử lý việc thực thi lệnh /start-work.                                                                                                                     |
| **auto-slash-command** | UserPromptSubmit | Tự động chạy slash commands từ prompt.                                                                                                                    |

#### Quality & Safety

| Hook                         | Event       | Mô tả                                                                     |
| ---------------------------- | ----------- | ------------------------------------------------------------------------- |
| **comment-checker**          | PostToolUse | Nhắc agent giảm comment quá nhiều. Tự bỏ qua BDD, directives, docstrings. |
| **thinking-block-validator** | PreToolUse  | Validate thinking blocks để tránh lỗi API.                                |
| **empty-message-sanitizer**  | PreToolUse  | Ngăn lỗi API do chat message rỗng.                                        |
| **edit-error-recovery**      | PostToolUse | Khôi phục khi Edit tool thất bại.                                         |

#### Recovery & Stability

| Hook                                        | Event | Mô tả                                                                           |
| ------------------------------------------- | ----- | ------------------------------------------------------------------------------- |
| **session-recovery**                        | Stop  | Khôi phục lỗi session - thiếu tool results, lỗi thinking block, empty messages. |
| **anthropic-context-window-limit-recovery** | Stop  | Xử lý Claude context window limits ổn định.                                     |
| **background-compaction**                   | Stop  | Auto-compacts sessions chạm giới hạn token.                                     |

#### Truncation & Context Management

| Hook                      | Event       | Mô tả                                                                         |
| ------------------------- | ----------- | ----------------------------------------------------------------------------- |
| **grep-output-truncator** | PostToolUse | Truncate output grep theo context window. Giữ 50% headroom, cap ở 50k tokens. |
| **tool-output-truncator** | PostToolUse | Truncate output từ Grep, Glob, LSP, AST-grep tools.                           |

#### Notifications & UX

| Hook                        | Event            | Mô tả                                                                                |
| --------------------------- | ---------------- | ------------------------------------------------------------------------------------ |
| **auto-update-checker**     | UserPromptSubmit | Kiểm tra version mới, hiển thị toast lúc startup với version và trạng thái Sisyphus. |
| **background-notification** | Stop             | Thông báo khi background agent tasks hoàn tất.                                       |
| **session-notification**    | Stop             | OS notifications khi agents idle. Hoạt động trên macOS, Linux, Windows.              |
| **agent-usage-reminder**    | PostToolUse      | Nhắc bạn tận dụng specialized agents để có kết quả tốt hơn.                          |

#### Task Management

| Hook                    | Event       | Mô tả                                           |
| ----------------------- | ----------- | ----------------------------------------------- |
| **task-resume-info**    | PostToolUse | Cung cấp thông tin resume task để giữ liên tục. |
| **delegate-task-retry** | PostToolUse | Retry các delegate_task bị lỗi.                 |

#### Integration

| Hook                         | Event      | Mô tả                                        |
| ---------------------------- | ---------- | -------------------------------------------- |
| **claude-code-hooks**        | All        | Chạy hooks từ settings.json của Claude Code. |
| **atlas**                    | All        | Logic điều phối chính (771 lines).           |
| **interactive-bash-session** | PreToolUse | Quản lý tmux sessions cho interactive CLI.   |
| **non-interactive-env**      | PreToolUse | Xử lý ràng buộc môi trường non-interactive.  |

#### Specialized

| Hook                   | Event       | Mô tả                                   |
| ---------------------- | ----------- | --------------------------------------- |
| **prometheus-md-only** | PostToolUse | Enforce Prometheus chỉ output Markdown. |

### Claude Code Hooks Integration

Chạy custom scripts qua `settings.json` của Claude Code:

```json
{
    "hooks": {
        "PostToolUse": [
            {
                "matcher": "Write|Edit",
                "hooks": [{ "type": "command", "command": "eslint --fix $FILE" }]
            }
        ]
    }
}
```

**Vị trí hooks**:

- `~/.claude/settings.json` (user)
- `./.claude/settings.json` (project)
- `./.claude/settings.local.json` (local, git-ignored)

### Disabling Hooks

Tắt hook cụ thể trong config:

```json
{
    "disabled_hooks": ["comment-checker", "auto-update-checker", "startup-toast"]
}
```

---

## Tools: Năng lực của tác nhân

### LSP Tools (tính năng IDE cho agents)

| Tool                    | Mô tả                                               |
| ----------------------- | --------------------------------------------------- |
| **lsp_diagnostics**     | Lấy errors/warnings trước build                     |
| **lsp_prepare_rename**  | Validate thao tác rename                            |
| **lsp_rename**          | Rename symbol trên toàn workspace                   |
| **lsp_goto_definition** | Nhảy tới nơi định nghĩa symbol                      |
| **lsp_find_references** | Tìm tất cả usages trên toàn workspace               |
| **lsp_symbols**         | Lấy outline của file hoặc tìm symbol trên workspace |

### AST-Grep Tools

| Tool                 | Mô tả                                   |
| -------------------- | --------------------------------------- |
| **ast_grep_search**  | Tìm pattern code theo AST (25 ngôn ngữ) |
| **ast_grep_replace** | Thay thế code theo AST                  |

### Delegation Tools

| Tool                  | Mô tả                                                       |
| --------------------- | ----------------------------------------------------------- |
| **call_omo_agent**    | Spawn explore/librarian agents. Hỗ trợ `run_in_background`. |
| **delegate_task**     | Delegate theo category hoặc chỉ định agent trực tiếp.       |
| **background_output** | Lấy kết quả background task                                 |
| **background_cancel** | Huỷ background tasks đang chạy                              |

### Session Tools

| Tool               | Mô tả                            |
| ------------------ | -------------------------------- |
| **session_list**   | Liệt kê OpenCode sessions        |
| **session_read**   | Đọc messages và lịch sử session  |
| **session_search** | Tìm kiếm full-text trên messages |
| **session_info**   | Lấy metadata và thống kê session |

### Interactive Terminal Tools

| Tool                 | Mô tả                                                                                                       |
| -------------------- | ----------------------------------------------------------------------------------------------------------- |
| **interactive_bash** | Terminal dựa trên tmux cho TUI apps (vim, htop, pudb). Truyền tmux subcommands trực tiếp (không có prefix). |

**Ví dụ**:

```bash
# Create a new session
interactive_bash(tmux_command="new-session -d -s dev-app")

# Send keystrokes to a session
interactive_bash(tmux_command="send-keys -t dev-app 'vim main.py' Enter")

# Capture pane output
interactive_bash(tmux_command="capture-pane -p -t dev-app")
```

**Điểm chính**:

- Commands là tmux subcommands (không có `tmux` prefix)
- Dùng cho interactive apps cần session tồn tại
- One-shot commands nên dùng Bash tool thường với `&`

---

## MCPs: Built-in Servers

### websearch (Exa AI)

Tìm kiếm web thời gian thực dựa trên [Exa AI](https://exa.ai).

### context7

Tra cứu tài liệu chính thức cho mọi thư viện/framework.

### grep_app

Tìm kiếm code siêu nhanh trên public GitHub repos. Rất hữu ích để tìm ví dụ triển khai.

### Skill-Embedded MCPs

Skills có thể mang MCP server riêng:

```yaml
---
description: Browser automation skill
mcp:
    playwright:
        command: npx
        args: ['-y', '@anthropic-ai/mcp-playwright']
---
```

Tool `skill_mcp` gọi các operations này với schema discovery đầy đủ.

#### OAuth-Enabled MCPs

Skills có thể định nghĩa remote MCP servers được bảo vệ bằng OAuth. Hỗ trợ OAuth 2.1 tuân thủ RFC (RFC 9728, 8414, 8707, 7591):

```yaml
---
description: My API skill
mcp:
    my-api:
        url: https://api.example.com/mcp
        oauth:
            clientId: ${CLIENT_ID}
            scopes: ['read', 'write']
---
```

Khi skill MCP có cấu hình `oauth`:

- **Auto-discovery**: Fetch `/.well-known/oauth-protected-resource` (RFC 9728), fallback sang `/.well-known/oauth-authorization-server` (RFC 8414)
- **Dynamic Client Registration**: Tự đăng ký với server hỗ trợ RFC 7591 (clientId trở thành tuỳ chọn)
- **PKCE**: Bắt buộc cho mọi flow
- **Resource Indicators**: Tự sinh từ MCP URL theo RFC 8707
- **Token Storage**: Lưu tại `~/.config/opencode/mcp-oauth.json` (chmod 0600)
- **Auto-refresh**: Refresh token khi 401; step-up authorization khi 403 với `WWW-Authenticate`
- **Dynamic Port**: OAuth callback server dùng port tự dò đang rảnh

Pre-authenticate qua CLI:

```bash
bunx oh-my-opencode mcp oauth login <server-name> --server-url https://api.example.com
```

---

## Context Injection

### Directory AGENTS.md

Tự inject AGENTS.md khi đọc file. Walk từ thư mục của file lên project root:

```
project/
├── AGENTS.md              # Injected first
├── src/
│   ├── AGENTS.md          # Injected second
│   └── components/
│       ├── AGENTS.md      # Injected third
│       └── Button.tsx     # Reading this injects all 3
```

### Conditional Rules

Inject rules từ `.claude/rules/` khi match điều kiện:

```markdown
---
globs: ['*.ts', 'src/**/*.js']
description: 'TypeScript/JavaScript coding rules'
---

- Use PascalCase for interface names
- Use camelCase for function names
```

Hỗ trợ:

- File `.md` và `.mdc`
- Trường `globs` để pattern matching
- `alwaysApply: true` cho rules áp dụng vô điều kiện
- Walk lên project root, đồng thời include `~/.claude/rules/`

---

## Tương thích Claude Code

Lớp tương thích đầy đủ cho cấu hình Claude Code.

### Config Loaders

| Loại         | Vị trí                                                     |
| ------------ | ---------------------------------------------------------- |
| **Commands** | `~/.claude/commands/`, `.claude/commands/`                 |
| **Skills**   | `~/.claude/skills/*/SKILL.md`, `.claude/skills/*/SKILL.md` |
| **Agents**   | `~/.claude/agents/*.md`, `.claude/agents/*.md`             |
| **MCPs**     | `~/.claude/.mcp.json`, `.mcp.json`, `.claude/.mcp.json`    |

MCP configs hỗ trợ mở rộng biến môi trường: `${VAR}`.

### Data Storage

| Dữ liệu     | Vị trí                   | Định dạng              |
| ----------- | ------------------------ | ---------------------- |
| Todos       | `~/.claude/todos/`       | Claude Code compatible |
| Transcripts | `~/.claude/transcripts/` | JSONL                  |

### Compatibility Toggles

Tắt các tính năng cụ thể:

```json
{
    "claude_code": {
        "mcp": false,
        "commands": false,
        "skills": false,
        "agents": false,
        "hooks": false,
        "plugins": false
    }
}
```

| Toggle     | Tắt                                        |
| ---------- | ------------------------------------------ |
| `mcp`      | `.mcp.json` files (giữ built-in MCPs)      |
| `commands` | `~/.claude/commands/`, `.claude/commands/` |
| `skills`   | `~/.claude/skills/`, `.claude/skills/`     |
| `agents`   | `~/.claude/agents/` (giữ built-in agents)  |
| `hooks`    | settings.json hooks                        |
| `plugins`  | Claude Code marketplace plugins            |

Tắt plugin cụ thể:

```json
{
    "claude_code": {
        "plugins_override": {
            "claude-mem@thedotmack": false
        }
    }
}
```
