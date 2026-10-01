# bcl/toolkit

The shared PHP layer under every one of my Laravel apps: Microsoft Entra SSO and
the domain allowlist, Passport/Sanctum token plumbing, the MCP `Tool` base with
instructions versioning, the org-agnostic brand registry (identity, theming and
per-brand mailers), capability tokens and TTL parsing, Filament panel glue, the
generic model capability traits, and the Pest contracts that keep consumers
honest. It is deliberately org-neutral — it ships an *empty* brand registry and
nothing that knows about a particular client; each app registers its own brands.
The point is that six apps speak one vocabulary, and a fix lands once.

## Install

The package isn't on Packagist. Add the VCS repository and pin a tagged release:

```json
{
    "require": {
        "bcl/toolkit": "^1.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/bullcitylearning/toolkit"
        }
    ]
}
```

```bash
composer require bcl/toolkit:^1.0
php artisan vendor:publish --tag=bcl-toolkit-config   # config/brands.php
```

The service provider is auto-discovered. Publishable tags: `bcl-toolkit-config`
(the brand registry) and `bcl-toolkit-views` (the Filament socialite buttons).

**Pin, don't track `dev-main`.** A pinned app adopts toolkit changes when
someone deliberately runs `composer update bcl/toolkit` and its own suite passes
— which is what lets one app take an improvement without four others changing
underneath it.

## Docs

| Doc | What's in it |
|---|---|
| [`docs/concerns.md`](docs/concerns.md) | The model capability traits — `LogsModelActivity` (activitylog defaults, secret and encrypted-cast exclusions), `HasMetaData` (the schemaless `metadata`/`md` escape hatch), `HasTranslations` (locale-correct `toArray()`). Setup, behavior, and how to override each. |

## MCP authorization

Since v1.3.0 the toolkit implements the **MCP authorization standard**
(`jims_dev_way/playbooks/new-laravel-app.md` step 5). The standard holds the
rules; this is the API:

- **`Bcl\Toolkit\Mcp\GatedTool`**: declare `capability()` (an ordinary Laravel
  ability such as `sends.approve`) and write `perform(Request $request, ...)`
  instead of `handle()`. The tool is hidden from callers without the ability
  and re-checked on every call. A refusal is `{"error":"forbidden","capability":"…"}`
  with `isError: true`, and an ability nobody defined is denied. Note that an
  app `Gate::before` that returns `true` for super-admins also allows undefined
  abilities. Plain `Tool` subclasses behave exactly as before.
- **Audit**: every gated call writes an activitylog row (`log_name` `mcp`,
  event `tool.called` or `tool.refused`, caused by the user). Its properties are
  server, tool, capability, `auth`/`client_id`/`token_id`, outcome
  (`ok|error|forbidden`) and the arguments, with keys matching
  `pass|secret|token|key|credential|authorization|cookie` redacted.
- **`Mcp::bclWeb(AppServer::class, admin: AdminServer::class)`** adds
  `/mcp/admin` (`auth:api`, the `mcp:admin` scope, `throttle:mcp-admin` at
  30/min). Its OAuth discovery advertises `mcp:admin`, so a connector pointed at
  it asks for that scope. `php artisan bcl:mcp-token --scope=mcp:admin
  --path=/mcp/admin` mints an admin PAT. Without `admin:` the routes are
  unchanged.
- **`Bcl\Toolkit\Testing\AssertsMcpAuthorization`** (`uses(...)` in Pest) adds
  `assertToolHiddenFrom`, `assertToolVisibleTo`, `assertToolRefusedFor`,
  `assertToolAudited` and `assertEveryToolGated`.

How it hooks laravel/mcp: `tools/call` looks tools up through the same
`shouldRegister()` filter as `tools/list`, so the toolkit binds its own
`CallTool` subclass. While a call to a gated tool is in flight, that tool stays
resolvable, and its final `handle()` does the refusing and auditing. It doesn't
change how any other tool is dispatched.

## Releases

Semver, tagged, `main` is the only branch:

- **patch** — fixes and docs; **minor** — new capability or a tightened default
  that doesn't change a documented API; **major** — anything a consumer must
  edit code for.
- Consumers pin `^1.0` and upgrade one app at a time, tests as the gate.
- Security-relevant changes say so in the tag notes, so a consumer reading
  `git tag -n` knows which update is not optional.

## Working on it

```bash
composer test              # Pest (orchestra/testbench — no host app needed)
composer pint -- --test    # lint check; drop --test to fix
```

Both must be green before tagging. CI runs the same pair plus a blocking
`composer audit` on every push and weekly on a schedule; it deploys nothing.

New shared code arrives here by promotion — a pattern proves itself in an app,
then graduates rather than getting copy-pasted into app three. The rules for
what belongs here (and what belongs in `laravel_base` or a shared JS package)
live in `jims_dev_way/playbooks/shared-code.md`; the model-trait style lives in
`playbooks/model-composition.md`.
