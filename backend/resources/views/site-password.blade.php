<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Private site: keep it out of search results while the gate is up. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>1paisakart</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 1.5rem; background: #f8fafc; color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .card {
            width: 100%; max-width: 24rem; background: #fff; border-radius: 1rem; padding: 2rem;
            box-shadow: 0 1px 3px rgb(15 23 42 / .08), 0 12px 32px rgb(15 23 42 / .06);
        }
        .brand { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 .25rem; }
        .brand span { color: #f59e0b; }
        .brand em { color: #34815e; font-style: normal; }
        p { margin: 0 0 1.5rem; color: #64748b; font-size: .875rem; }
        label { display: block; font-size: .8125rem; font-weight: 600; margin-bottom: .375rem; }
        input {
            width: 100%; padding: .625rem .75rem; font-size: 1rem; color: inherit;
            border: 1px solid #cbd5e1; border-radius: .5rem; background: #fff;
        }
        input:focus { outline: 2px solid #34815e; outline-offset: 1px; border-color: #34815e; }
        button {
            width: 100%; margin-top: 1rem; padding: .6875rem 1rem; font-size: 1rem; font-weight: 600;
            color: #fff; background: #34815e; border: 0; border-radius: .5rem; cursor: pointer;
        }
        button:hover { background: #2b6b4e; }
        .error {
            margin: 0 0 1rem; padding: .625rem .75rem; border-radius: .5rem;
            background: #fef2f2; color: #b91c1c; font-size: .8125rem;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1 class="brand"><em>1paisa</em><span>kart</span></h1>
        <p>This site is private. Enter the password to continue.</p>

        @if (! empty($error))
            <p class="error">That password isn't right. Try again.</p>
        @endif

        <form method="POST" action="/__unlock">
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirect ?? '/' }}">
            <label for="site_password">Password</label>
            {{-- autofocus + current-password so managers offer to save it --}}
            <input id="site_password" name="site_password" type="password"
                   autocomplete="current-password" autofocus required>
            <button type="submit">Enter</button>
        </form>
    </main>
</body>
</html>
