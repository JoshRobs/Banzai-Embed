#!/usr/bin/env bash
# Upload every fixture through the real admin form, the way a person would.
#
#   npx @wordpress/env start
#   npx @wordpress/env run cli php wp-content/plugins/Banzai-Embed/tests/make-zips.php
#   bash tests/e2e.sh
#
# Creates one app per fixture (slug = fixture name) and prints where each
# upload redirected to. Inspect the results with:
#   npx @wordpress/env run cli wp option get banzaiembed_apps --format=json
set -euo pipefail

BASE="${BASE:-http://localhost:8890}"
DIR="$(cd "$(dirname "$0")" && pwd)"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

curl -s -c "$JAR" -b "$JAR" "$BASE/wp-login.php" >/dev/null
curl -s -c "$JAR" -b "$JAR" -o /dev/null \
	--data-urlencode "log=admin" --data-urlencode "pwd=password" \
	--data-urlencode "testcookie=1" --data-urlencode "redirect_to=$BASE/wp-admin/" \
	"$BASE/wp-login.php"

nonce() {
	curl -s -b "$JAR" "$BASE/wp-admin/admin.php?page=banzaiembed-new" |
		grep -o 'name="_wpnonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'
}

upload() { # slug framework zip
	local n file="$3"
	# Git Bash's curl is a Windows binary and cannot open /c/... paths.
	command -v cygpath >/dev/null && file="$(cygpath -m "$file")"
	n="$(nonce)"
	[ -n "$n" ] || { echo "no nonce — not logged in?"; exit 1; }
	curl -s -b "$JAR" -o /dev/null -w "%{http_code} %{redirect_url}\n" \
		-F "action=banzaiembed_save_app" -F "_wpnonce=$n" -F "original_slug=" \
		-F "name=$1" -F "slug=$1" -F "framework=$2" -F "mount_id=" \
		-F "build=@$file;type=application/zip" \
		"$BASE/wp-admin/admin-post.php"
}

for zip in "$DIR"/output/*.zip; do
	slug="$(basename "$zip" .zip)"
	fw=other
	case "$slug" in vue*) fw=vue ;; react*|cra) fw=react ;; esac
	printf '%-26s ' "$slug"
	upload "$slug" "$fw" "$zip"
done
