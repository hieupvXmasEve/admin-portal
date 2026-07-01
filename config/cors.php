<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // The MCP OAuth discovery + token + DCR endpoints and the MCP server endpoint are
    // fetched cross-origin from browser-based agent clients (e.g. the MCP Inspector at
    // localhost:6274), so they need CORS headers (ADR-0011). Step 5 hardening tightens
    // the allowed origins for production.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth', '.well-known/*', 'oauth/*', 'mcp/*'],

    'allowed_methods' => ['*'],

    // `https://chatgpt.com` (and the legacy `https://chat.openai.com`) are added because
    // ChatGPT's "add custom connector" modal probes the MCP server + OAuth discovery
    // endpoints from the browser (chatgpt.com origin); without an Access-Control-Allow-Origin
    // match the browser blocks the read and ChatGPT reports "does not implement OAuth".
    'allowed_origins' => [env('VITE_APP_URL_FE', 'http://localhost:3000'), env('VITE_APP_URL_FE_LECTURE', 'http://localhost:3001'), 'http://localhost:3000', 'http://localhost:6274', 'http://127.0.0.1:6274', 'https://chatgpt.com', 'https://chat.openai.com'],

    'allowed_origins_patterns' => ['#^https?://.*\.asia-vn\.edu\.vn$#', '#^https?://.*\.metropolia\.edu\.vn$#', '#^http://localhost:3000$#', '#^http://127.0.0.1:3000$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['WWW-Authenticate'],

    'max_age' => 0,

    'supports_credentials' => true,

];
