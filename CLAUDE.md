# Blackstar — working notes for Claude

Logistics / fleet layer for the Black Market Coalition. A **Fleetbase fork**:
Laravel API (PHP `>=8.0 <8.3`) under `api/`, Ember console under `console/`.

---

## Read this before you trust a green checkmark

### CI has nine workflow files. Historically, none of them had ever run.

`.github/workflows/` contains `ci.yml`, `cd.yml`, `cloud.yml`, `eks-cd.yml`,
`gcp-cd.yml`, `build-binaries.yml`, `publish-docker-images.yml`,
`create-release.yml`, `discord-announcement.yml`. Actions were not enabled on
the repository, so the files existed and executed zero times.

**Consequence: the absence of a red check here has never meant anything.** Do
not treat "CI is green" as evidence in this repo without first confirming a run
actually happened for that commit. Check the Actions tab, or the run list via
the API — a workflow file is not a workflow run.

If Actions have since been enabled, expect the first run to surface a backlog of
failures that accumulated unobserved. That is not your change breaking things.

### Therefore: run the tests locally, and say which ones you ran

```bash
cd api
php -l path/to/File.php                    # syntax only — not a test
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature
```

`api/phpunit.xml` defines two suites, `Unit` and `Feature` (25 `*Test.php` files
today). Fixtures live in `api/tests/Fixtures/`, including a
`freeblackmarket/` fixture set for the FBM integration surface.

Be precise in what you claim: "syntax-checked with `php -l`" and "the Unit suite
passes" are different statements, and past work here has only managed the first.
Say which one you did.

---

## Layout

```
api/            Laravel — app/, routes/, config/, database/, tests/
console/        Ember admin console (+ its own nginx.conf)
apps/           blackstar-nav, ENV_CONVENTIONS.md
docker/         compose + bake definitions
```

`api/app/Support/Geo.php` holds distance/geography helpers — nodes carry a
geography so `service_radius` is meaningful rather than decorative. Reuse it
rather than recomputing haversine inline.

Upstream-fork notes are in `TRANSMUTATION_NOTES.md` and `CONSOLIDATION.md`.
There's a `PULL_REQUEST_TEMPLATE.md` at the repo root — populate its headings.

---

## nginx

`console/nginx.conf` is small (3 `add_header` across 4 blocks). If you add
headers here, know the nginx rule: an `add_header` in an inner block
**discards all inherited `add_header` directives** from the outer block rather
than adding to them. Verify with `nginx -t` plus an actual response-header
check, not by reading the file.

(The large header-redefinition backlog is in the **Blackout** repo's infra
configs, not this one.)

---

## Working conventions

- Default branch is `main`.
- Verify a claim against the tree before writing it down — this repo has had
  suspected bugs that turned out to be correct on a full read of the call path
  (e.g. `created_by_user_id` *is* set; check the caller before reporting a
  NOT NULL violation).
- Don't report a test suite as passing unless you ran it.
- Never disable TLS verification or unset `HTTPS_PROXY`.
