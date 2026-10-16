#!/usr/bin/env bash
#
# One-shot production deploy: pulls the latest code, adds any new fields,
# reseeds content, syncs projects from Airtable and (re)registers its webhook,
# and rebuilds the cache — everything after `git push` from your machine, in a
# single command run on the server.
#
# Run from the via-cms directory (wherever this repo is checked out, e.g.
# ~/viacms.dtecsoftwaresolutions.com):
#
#   ./scripts/deploy.sh            # pull + fields + seed + cache rebuild
#   ./scripts/deploy.sh --prune    # same, and also deletes stale nodes
#
# --prune is left off by default on purpose: a stale node is only ever one
# that this exact run's seed data did not touch, but deletion is still
# deletion. Run once without it, read the list it prints, then re-run with
# --prune once you're satisfied nothing on it is content you meant to keep.
#
# Deliberately does NOT run `drush config:export` or `drush config:import` —
# this server's active configuration diverges from config/sync in ways that
# predate this script (see the add-*-fields.php scripts' doc comments), so
# `cim` is unsafe here until that's reconciled separately. Every field this
# script adds goes through the Drupal API instead, which only ever adds.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> git pull"
before=$(git rev-parse HEAD)
git pull
after=$(git rev-parse HEAD)

# Bash reads this file incrementally as it runs, not all at once — if git pull
# just rewrote it underneath the running process, the interpreter's read
# position can land mid-line in the new version and silently skip or garble
# whatever comes next. (This is exactly what happened the first time this ran:
# it jumped straight from add-page-hero-fields.php to seed-content.php,
# skipping add-gallery-fields.php entirely, with no error.) Re-exec into the
# freshly pulled copy so everything after this point is read cleanly from a
# file that will not change again mid-run.
if [[ "$before" != "$after" ]]; then
  echo "    new commits pulled — restarting from the updated script"
  exec bash "$0" "$@"
fi

echo "==> add-project-fields.php"
./vendor/bin/drush php:script scripts/add-project-fields.php

echo "==> add-page-hero-fields.php"
./vendor/bin/drush php:script scripts/add-page-hero-fields.php

echo "==> add-gallery-fields.php"
./vendor/bin/drush php:script scripts/add-gallery-fields.php

echo "==> add-story-fields.php"
./vendor/bin/drush php:script scripts/add-story-fields.php

echo "==> add-airtable-sync-fields.php"
./vendor/bin/drush php:script scripts/add-airtable-sync-fields.php

echo "==> add-team-member-type-field.php"
./vendor/bin/drush php:script scripts/add-team-member-type-field.php

echo "==> add-team-member-email-field.php"
./vendor/bin/drush php:script scripts/add-team-member-email-field.php

echo "==> build-content-model.php"
./vendor/bin/drush php:script scripts/build-content-model.php

echo "==> set-site-favicon.php"
./vendor/bin/drush php:script scripts/set-site-favicon.php

echo "==> sync-environment.php"
./vendor/bin/drush php:script scripts/sync-environment.php

echo "==> seed-content.php"
if [[ "${1:-}" == "--prune" ]]; then
  SEED_PRUNE=1 ./vendor/bin/drush php:script scripts/seed-content.php
else
  ./vendor/bin/drush php:script scripts/seed-content.php
fi

echo "==> sync-airtable-projects.php"
./vendor/bin/drush php:script scripts/sync-airtable-projects.php

echo "==> add-content-placement.php"
./vendor/bin/drush php:script scripts/add-content-placement.php

echo "==> setup-airtable-webhook.php"
./vendor/bin/drush php:script scripts/setup-airtable-webhook.php

echo "==> ensure-cors-origins.php"
./vendor/bin/drush php:script scripts/ensure-cors-origins.php

echo "==> cache:rebuild"
./vendor/bin/drush cache:rebuild

echo "==> invalidate web container and bootstrap caches"
./vendor/bin/drush php:eval "\Drupal::database()->truncate('cache_container')->execute(); \Drupal::database()->truncate('cache_bootstrap')->execute();"

API_URL="${VIA_API_URL:-https://viacms.dtecsoftwaresolutions.com/api/v1/site-content}"
echo "==> verifying CORS origins"
for origin in \
  "https://viafoundation.dtecsoftwaresolutions.com" \
  "https://via-foundation.org" \
  "https://www.via-foundation.org"; do
  headers=$(curl --silent --show-error --dump-header - --output /dev/null --max-time 30 \
    --request OPTIONS "$API_URL" \
    --header "Origin: $origin" \
    --header "Access-Control-Request-Method: GET" \
    --header "Access-Control-Request-Headers: accept")
  if ! printf '%s\n' "$headers" | grep --fixed-strings --ignore-case --quiet "Access-Control-Allow-Origin: $origin"; then
    echo "CORS check failed for $origin." >&2
    echo "Add it to web/sites/default/services.yml on this server and rerun deploy.sh." >&2
    exit 1
  fi
  echo "  allowed: $origin"
done

echo
echo "==> verifying live API"
curl -s "$API_URL" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
if (!$d) { fwrite(STDERR, "Could not parse API response.\n"); exit(1); }
foreach (["heroStats","impactCards","partners","announcements","countries","pageHeroes","gallery","projects","news","stories","team","jobPostings","restorationTimeline"] as $k) {
  printf("  %-12s %d\n", $k, count($d[$k] ?? []));
}
'

echo
echo "Done."
if [[ "${1:-}" != "--prune" ]]; then
  echo "If seed-content.php listed stale nodes above, review them, then re-run: ./scripts/deploy.sh --prune"
fi
