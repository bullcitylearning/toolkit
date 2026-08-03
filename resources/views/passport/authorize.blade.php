<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); padding: 2rem; max-width: 420px; width: 100%; }
        h2 { margin: 0 0 0.5rem; font-size: 1.25rem; }
        p { color: #6b7280; margin: 0 0 1.5rem; font-size: 0.875rem; }
        .client { font-weight: 600; color: #111827; }
        .scopes { background: #f9fafb; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; list-style: none; }
        .scopes li { color: #374151; font-size: 0.875rem; padding: 0.25rem 0; }
        .buttons { display: flex; gap: 0.75rem; }
        button { flex: 1; padding: 0.625rem 1rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; border: none; }
        .approve { background: #2563eb; color: white; }
        .approve:hover { background: #1d4ed8; }
        .deny { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .deny:hover { background: #e5e7eb; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Authorization Request</h2>
        <p><span class="client">{{ $client->name }}</span> is requesting access to your {{ config('app.name') }} account.</p>

        @if (count($scopes) > 0)
            <ul class="scopes">
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        <div class="buttons">
            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="approve">Authorize</button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
                <button type="submit" class="deny">Deny</button>
            </form>
        </div>
    </div>
</body>
</html>
