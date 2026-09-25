#!/usr/bin/env bash
# Substitui `uses: owner/action@tag` pelo SHA imutável do commit (mitiga ataques de tag hijacking).
# Requisitos: gh CLI autenticado.
set -euo pipefail
cd "$(dirname "$0")/.."

grep -rhoE 'uses: [A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+@v[0-9][A-Za-z0-9_.-]*' .github/workflows | sort -u | while read -r _ ref; do
  repo="${ref%@*}"; tag="${ref#*@}"
  obj=$(gh api "repos/$repo/git/ref/tags/$tag" --jq '.object.sha + " " + .object.type')
  sha="${obj% *}"; type="${obj#* }"
  # Tags anotadas apontam para um objeto tag -> resolve até o commit
  [ "$type" = "tag" ] && sha=$(gh api "repos/$repo/git/tags/$sha" --jq '.object.sha')
  echo "📌 $repo@$tag -> $sha"
  sed -i.bak "s#uses: $repo@$tag\$#uses: $repo@$sha \# $tag#" .github/workflows/*.yml
done
rm -f .github/workflows/*.bak
echo "✅ Actions fixadas por SHA. Revise o diff e abra um PR."
