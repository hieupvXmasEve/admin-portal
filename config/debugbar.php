<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Debugbar Settings
     |--------------------------------------------------------------------------
     |
     | Debugbar is enabled by default, when debug is set to true in app.php.
     | You can override the value by setting enable to true or false instead of null.
     |
     | You can provide an array of URI's that must be ignored (eg. 'api/*')
     |
     */

    'enabled' => env('DEBUGBAR_ENABLED', false),
    'except' => [
        'telescope*',
        'horizon*',
    ],

    /*
     |--------------------------------------------------------------------------
     | Storage settings
     |--------------------------------------------------------------------------
     |
     | DebugBar stores data for session/ajax requests.
     | You can disable this, so the debugbar stores data in headers/session,
     | but this can cause problems with large data collectors.
     | By default, file storage (in the storage folder) is used. Redis and PDO
     | can also be used. For PDO, run the package migrations first.
     |
     */
    'storage' => [
        'enabled' => false,
        'driver' => 'file', // redis, file, pdo, socket, custom
        'path' => storage_path('debugbar'), // For file driver
        'connection' => null,   // Leave null for default connection (Redis/PDO)
        'provider' => '', // Instance of StorageInterface for custom driver
        'hostname' => '127.0.0.1', // Hostname to use with the "socket" driver
        'port' => 2304, // Port to use with the "socket" driver
    ],

    /*
     |--------------------------------------------------------------------------
     | Editor
     |--------------------------------------------------------------------------
     |
     | Choose your preferred editor to use when clicking file name.
     |
     | Supported: "phpstorm", "vscode", "vscode-insiders", "vscode-remote",
     |            "vscode-tunnel", "sublime", "textmate", "emacs", "macvim", "idea",
     |            "netbeans", "atom"
     |
     */
    'editor' => env('DEBUGBAR_EDITOR', 'phpstorm'),

    /*
     |--------------------------------------------------------------------------
     | Remote Path Mapping
     |--------------------------------------------------------------------------
     |
     | If you are using a remote dev server, like Laravel Homestead, Docker, or
     | even a remote VPS, it will be necessary to specify your path mapping.
     |
     | Leaving one or both of these null will not trigger the remote feature.
     |
     | "remote_sites_path" is an absolute base path for your sites or projects
     | in Homestead, Vagrant, Docker, etc.
     |
     | "local_sites_path" is an absolute base path for your sites or projects
     | on your local computer where your IDE or editor is running on.
     |
     */
    'remote_sites_path' => env('DEBUGBAR_REMOTE_SITES_PATH', ''),
    'local_sites_path' => env('DEBUGBAR_LOCAL_SITES_PATH', ''),

    /*
     |--------------------------------------------------------------------------
     | Vendors
     |--------------------------------------------------------------------------
     |
     | Vendor files are included by default, but can be set to false.
     | This can also be set to 'js' or 'css', to only include javascript or css vendor files.
     | Vendor files are for css: font-awesome (including fonts) and highlight.js (css files)
     | and for js: jquery and highlight.js
     | So if you want syntax highlighting, set it to true.
     | jQuery is set to not conflict with existing jQuery scripts.
     |
     */
    'include_vendors' => false,

    /*
     |--------------------------------------------------------------------------
     | Capture Ajax Requests
     |--------------------------------------------------------------------------
     |
     | The Debugbar can capture Ajax requests and display them. If you don't want this (ie. because of errors),
     | you can use this option to disable sending the data through the headers.
     |
     | Optionally, you can also send ServerTiming headers on ajax requests for the Chrome DevTools.
     |
     | Note for your request to be identified as ajax requests they must either send the X-Requested-With header
     | or have a content-type of application/json.
     |
     */
    'capture_ajax' => false,
    'add_ajax_timing' => false,

    /*
     |--------------------------------------------------------------------------
     | Custom Error Handler for Deprecated warnings
     |--------------------------------------------------------------------------
     |
     | When enabled, the Debugbar shows deprecated warnings for Symfony components
     | in the Messages tab.
     |
     */
    'error_handler' => false,

    /*
     |--------------------------------------------------------------------------
     | Clockwork integration
     |--------------------------------------------------------------------------
     |
     | The Debugbar can emulate the Clockwork headers, so you can use the Chrome
     | Extension, without the server-side code. It uses Debugbar collectors instead.
     |
     */
    'clockwork' => false,

    /*
     |--------------------------------------------------------------------------
     | DataCollectors
     |--------------------------------------------------------------------------
     |
     | Enable/disable DataCollectors
     |
     */

    'collectors' => [
        'phpinfo' => false,
        'messages' => false,
        'time' => false,
        'memory' => false,
        'exceptions' => false,
        'log' => false,
        'db' => false,
        'views' => false,
        'route' => false,
        'auth' => false,
        'gate' => false,
        'session' => false,
        'symfony_request' => false,
        'mail' => false,
        'laravel' => false,
        'events' => false,
        'default_request' => false,
        'logs' => false,
        'files' => false,
        'config' => false,
        'cache' => false,
        'models' => false,
        'livewire' => false,
    ],

    /*
     |--------------------------------------------------------------------------
     | Extra options
     |--------------------------------------------------------------------------
     |
     | Configure some DataCollectors
     |
     */

    'options' => [
        'auth' => [
            'show_name' => false,   // Also show the users name/email in the debugbar
        ],
        'db' => [
            'with_params' => false,   // Render SQL with the parameters substituted
            'backtrace' => false,   // Use a backtrace to find the origin of the query in your files.
            'backtrace_exclude_paths' => [],   // Paths to exclude from backtrace. (in addition to defaults)
            'timeline' => false,  // Add the queries to the timeline
            'duration_background' => true,   // Show shaded background on each query relative to how long it took.
            'explain' => [                 // Show EXPLAIN output on queries
                'enabled' => false,
                'types' => ['SELECT'],     // Deprecated setting, is always only SELECT
            ],
            'hints' => false,    // Show hints for common mistakes
            'show_copy' => false,    // Show copy button next to the query
            'slow_threshold' => false,   // Only track queries that last longer than this time in ms
        ],
        'mail' => [
            'full_log' => false,
        ],
        'views' => [
            'timeline' => false,  // Add the views to the timeline
            'data' => false,    // Note: Can slow down the application, because the data can be quite large..
        ],
        'route' => [
            'label' => false,  // show complete route on bar
        ],
        'logs' => [
            'file' => null,
        ],
        'cache' => [
            'values' => false, // collect cache values
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Inject Debugbar in Response
     |--------------------------------------------------------------------------
     |
     | Usually, the debugbar is added just before </body>, by listening to the
     | Response after the App is done. If you disable this, you have to add them
     | in your template yourself. See http://phpdebugbar.com/docs/rendering.html
     |
     */

    'inject' => false,

    /*
     |--------------------------------------------------------------------------
     | DebugBar route prefix
     |--------------------------------------------------------------------------
     |
     | Sometimes you want to set route prefix to be used by DebugBar to load
     | its resources from. Usually the need comes from misconfigured web server or
     | from trying to overcome bugs like this: http://trac.nginx.org/nginx/ticket/97
     |
     */
    'route_prefix' => '_debugbar',

    /*
     |--------------------------------------------------------------------------
     | DebugBar route domain
     |--------------------------------------------------------------------------
     |
     | By default DebugBar route served from the same domain that request served.
     | To override default domain, specify it as a non-empty value.
     */
    'route_domain' => null,

    /*
     |--------------------------------------------------------------------------
     | DebugBar theme
     |--------------------------------------------------------------------------
     |
     | Switches between light and dark theme. If set to auto it will respect system preferences
     | Possible values: auto, light, dark
     */
    'theme' => env('DEBUGBAR_THEME', 'auto'),
];
