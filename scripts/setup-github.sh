#!/usr/bin/env bash
# ============================================================================
# Cria o repositório público no GitHub, sobe main + develop e aplica
# proteção de branch (exigência do Tech Challenge: tudo em main via PR).
# Requisitos: GitHub CLI autenticado (gh auth login)
# Uso: ./scripts/setup-github.sh meu-usuario/oficina-soat
# ============================================================================
set -euo pipefail

REPO="${1:?Uso: $0 <owner>/<repo>}"
cd "$(dirname "$0")/.."

if [ ! -d .git ]; then
  git init -b main
fi

git add .
git commit -m "chore: bootstrap do projeto (Laravel 13 + Docker + CI)" || true

echo "▶ Criando repositório público $REPO..."
gh repo create "$REPO" --public --source=. --remote=origin --push

echo "▶ Criando branch develop..."
git switch -c develop
git push -u origin develop
git switch main

echo "▶ Configurações do repositório..."
gh api -X PATCH "repos/$REPO" \
  -F delete_branch_on_merge=true \
  -F allow_squash_merge=true \
  -F allow_merge_commit=false \
  -F allow_rebase_merge=false >/dev/null

# Secret scanning + push protection (gratuitos em repositórios públicos)
gh api -X PATCH "repos/$REPO" --input - >/dev/null <<JSON || true
{"security_and_analysis":{"secret_scanning":{"status":"enabled"},"secret_scanning_push_protection":{"status":"enabled"}}}
JSON
gh api -X PUT "repos/$REPO/vulnerability-alerts" >/dev/null || true

protect() {
  local branch="$1"
  echo "▶ Protegendo $branch..."
  gh api -X PUT "repos/$REPO/branches/$branch/protection" --input - >/dev/null <<JSON
{
  "required_status_checks": {
    "strict": true,
    "contexts": ["Lint & análise estática", "Testes & cobertura", "Análise de vulnerabilidades"]
  },
  "enforce_admins": true,
  "required_pull_request_reviews": {
    "required_approving_review_count": 1,
    "dismiss_stale_reviews": true
  },
  "restrictions": null,
  "required_linear_history": true,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "required_conversation_resolution": true
}
JSON
}

protect main
protect develop

echo ""
echo "✅ Repositório: https://github.com/$REPO"
echo "   Adicione os integrantes: gh api -X PUT repos/$REPO/collaborators/<username> -f permission=push"
