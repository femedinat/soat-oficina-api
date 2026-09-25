# scripts/

| Script | Quem roda | Quando |
|---|---|---|
| `bootstrap.sh` | Só quem cria o repo | Uma única vez, antes do primeiro push |
| `setup-github.sh` | Só quem cria o repo | Após o bootstrap: cria repo, develop e proteções |
| `pin-actions.sh` | Qualquer um | Após o setup e sempre que atualizar actions |

Integrantes do grupo: **não rodem estes scripts**. Clonem e rodem `make setup`.
