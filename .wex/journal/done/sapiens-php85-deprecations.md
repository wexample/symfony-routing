# PHP 8.5 implicit-nullable deprecations

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Reported by the `symfony-forms` agent (commit `3c06379`, read-only fields for the Sapiens app): 7 implicit-nullable deprecations raised on PHP 8.5 across this package and `symfony-loader`, surfacing in every suite that boots them.

## Task

Find every parameter typed non-nullable with a `= null` default and make it explicitly nullable (`Type $x = null` → `?Type $x = null`). A simple grep found none on one line here, so run the suite on PHP 8.5 with deprecations displayed — multi-line signatures escape the grep.

## Resolution

Closed: 2026-10-01

`src/Routing/TemplateBasedRouteLoader.php`: `string $env = null` (constructor) and `string $type = null` (`loadOnce`) are now `?string`. Both sat on their own line in multi-line signatures, hence the grep miss. Nothing else in `src/` or `tests/`; `php -l` clean on `php:8.5-cli`, suite not run.

`symfony-loader` was already fixed in its commit `92ff57c`. The remaining deprecations come from `symfony-testing` (6) and `symfony-translations` (1), per that package's done note — each package's own job.
