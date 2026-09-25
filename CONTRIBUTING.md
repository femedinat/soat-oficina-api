# Como contribuir

## Fluxo de branches
```
main      ← protegida, só recebe PR de develop (versões entregues, com tag)
develop   ← protegida, integração contínua do grupo
feature/<contexto>-<descricao>   ex.: feature/service-order-budget
fix/<descricao>
```

1. `git switch develop && git pull`
2. `git switch -c feature/inventory-stock-control`
3. Commit seguindo **Conventional Commits**: `feat(inventory): baixa de estoque na aprovação`
4. `make check` antes do push (lint + análise estática + cobertura ≥ 80% + audit)
5. Abra PR para `develop`. Precisa de **1 aprovação** e **CI verde**.

Tipos: `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `ci`.

## Regras de código
- `declare(strict_types=1);` em todo arquivo PHP.
- Nada de Laravel dentro de `src/*/Domain` (sem Eloquent, facades ou helpers).
- Regra de negócio mora no domínio, não no controller.
- Toda regra nova nasce com teste.
- Nunca commite `.env`, tokens ou dados pessoais reais — use o gerador de CPF/CNPJ de teste.

## Entrega final
`develop → main` via PR, depois `git tag v1.0.0-fase1` e informar branch + hash no PDF.
