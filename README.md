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
