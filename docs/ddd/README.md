# Documentação DDD

| Artefato | Arquivo | Link externo |
|---|---|---|
| Event Storming (criação da OS, acompanhamento, peças e insumos) | `event-storming.pdf` | _link do Miro_ |
| Diagramas (context map, agregados) | `diagramas.pdf` | _link do Miro_ |
| Linguagem Ubíqua e glossário | [`linguagem-ubiqua.md`](linguagem-ubiqua.md) | — |

> ⚠️ O board do Miro precisa estar com acesso "qualquer pessoa com o link pode ver".

## Bounded Contexts (proposta inicial — validar no Event Storming)

| Contexto | Pasta | Responsabilidade |
|---|---|---|
| Atendimento (OS) | `src/ServiceOrder` | Ciclo de vida da OS, orçamento, aprovação, status |
| Clientes e Veículos | `src/Customer` | Cadastro de clientes (CPF/CNPJ) e veículos |
| Estoque | `src/Inventory` | Peças, insumos, saldo, reserva e baixa |
| Catálogo | `src/Catalog` | Serviços oferecidos e preço base |
| Identidade | `src/Identity` | Usuários administrativos e autenticação JWT |
| Shared Kernel | `src/Shared` | Value Objects comuns (Document, LicensePlate, Money) |

## Camadas (por contexto)

```
src/<Contexto>/
├── Domain/          # Entidades, VOs, agregados, eventos, interfaces de repositório. ZERO dependência do Laravel.
├── Application/     # Casos de uso (commands/handlers), DTOs, orquestração de transações.
├── Infrastructure/  # Eloquent, repositórios concretos, integrações externas.
└── Interfaces/      # Controllers HTTP, Form Requests, Resources, rotas.
```

Regra de dependência: `Interfaces → Application → Domain ← Infrastructure`.
O domínio não conhece o framework — é isso que garante testes unitários rápidos e a cobertura de 80%.
