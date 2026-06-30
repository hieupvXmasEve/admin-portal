# Swinx ToolDispatcher is the AI execution boundary

AI providers may propose structured tool calls for Staff Copilot, but Swinx executes every data-access step through its own ToolDispatcher. We chose this over provider-native tools or direct provider access to application APIs because Swinx must validate permissions, campus scope, domain data permissions, schemas, limits, redaction, and audit evidence before any Academic or Finance data is queried.

The provider never receives database credentials, source API credentials, unrestricted tool access, or authority to call Swinx data sources directly. Future MCP exposure may map internal tools outward, but the internal ToolDispatcher remains the product enforcement boundary.
