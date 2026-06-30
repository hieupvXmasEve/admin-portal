<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize connection · Swinx</title>
    <style>
        :root {
            --surface: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --brand: #1d4ed8;
            --brand-ink: #ffffff;
            --warn-bg: #fffbeb;
            --warn-line: #fde68a;
            --warn-ink: #92400e;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: var(--ink);
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 10px 30px -12px rgba(15, 23, 42, .25);
            overflow: hidden;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
        }
        .brand .mark {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: var(--brand);
            color: var(--brand-ink);
            display: grid; place-items: center;
            font-weight: 700; font-size: 15px;
        }
        .brand strong { font-size: 15px; letter-spacing: .2px; }
        .body { padding: 24px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        p.lead { margin: 0 0 18px; color: var(--muted); font-size: 14px; line-height: 1.5; }
        .third-party {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 8px;
            font-size: 11px; font-weight: 600;
            color: var(--warn-ink);
            background: var(--warn-bg);
            border: 1px solid var(--warn-line);
            border-radius: 999px;
            vertical-align: middle;
        }
        dl { margin: 0 0 18px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .row { display: flex; justify-content: space-between; gap: 16px; padding: 12px 14px; border-bottom: 1px solid var(--line); }
        .row:last-child { border-bottom: 0; }
        dt { color: var(--muted); font-size: 13px; }
        dd { margin: 0; font-size: 13px; font-weight: 600; word-break: break-all; text-align: right; }
        .scopes { margin: 0 0 20px; padding: 0; list-style: none; }
        .scopes li { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; padding: 4px 0; }
        .scopes li::before { content: "✓"; color: var(--brand); font-weight: 700; }
        .scopes .none { color: var(--muted); font-style: italic; }
        .actions { display: flex; gap: 10px; }
        button {
            flex: 1;
            padding: 11px 14px;
            font-size: 14px; font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .approve { background: var(--brand); color: var(--brand-ink); }
        .deny { background: #fff; color: var(--danger); border-color: var(--line); }
        form { margin: 0; }
        .actions form { flex: 1; }
        .actions form button { width: 100%; }
    </style>
</head>
<body>
    <main class="card">
        <header class="brand">
            <span class="mark">S</span>
            <strong>Swinx</strong>
        </header>
        <div class="body">
            <h1>Authorize connection</h1>
            <p class="lead">
                <strong>{{ $client->name }}</strong>
                <span class="third-party">Third-party application</span>
                is requesting access to your Swinx account. It will be able to act on your behalf,
                limited to the data you are already permitted to see.
            </p>

            <dl>
                <div class="row">
                    <dt>Application</dt>
                    <dd>{{ $client->name }}</dd>
                </div>
                <div class="row">
                    <dt>Redirect URI</dt>
                    <dd>{{ is_array($client->redirect_uris) ? implode(', ', $client->redirect_uris) : $client->redirect_uris }}</dd>
                </div>
                <div class="row">
                    <dt>Signed in as</dt>
                    <dd>{{ $user->name }}</dd>
                </div>
            </dl>

            <strong style="font-size:13px;">Access being granted</strong>
            <ul class="scopes">
                @forelse ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @empty
                    <li class="none">Read-only access to Swinx tools you are already permitted to use.</li>
                @endforelse
            </ul>

            <div class="actions">
                <form method="post" action="{{ route('passport.authorizations.approve') }}">
                    @csrf
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="approve">Authorize</button>
                </form>
                <form method="post" action="{{ route('passport.authorizations.deny') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="state" value="{{ $request->state }}">
                    <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">
                    <button type="submit" class="deny">Cancel</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
