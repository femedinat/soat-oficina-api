# Documentação DDD

| Artefato | Arquivo | Link externo |
|---|---|---|
| Event Storming (criação da OS, acompanhamento, clientes, veículos, serviços, peças e insumos) | [`event-storming.pdf`](event-storming.pdf) | _link do Miro_ |
| Diagramas (context map, agregados, C4) | [`diagramas.pdf`](diagramas.pdf) | _link do Miro_ |
| Linguagem Ubíqua e glossário | [`linguagem-ubiqua.md`](linguagem-ubiqua.md) | — |

> ⚠️ O board do Miro precisa estar com acesso "qualquer pessoa com o link pode ver".

## Event Storming por Agregado (`docs/ddd/event-storming/`)

Para facilitar a leitura, manutenção modular pela equipe e rastreabilidade direta com o código:

| Agregado | Contexto | Documentação Detalhada |
|---|---|---|
| **Veículo** | Clientes e Veículos (`src/Customer`) | [`event-storming/veiculo.md`](event-storming/veiculo.md) |
| **Cliente** | Clientes e Veículos (`src/Customer`) | [`event-storming/cliente.md`](event-storming/cliente.md) |
| **Ordem de Serviço (OS)** | Atendimento (`src/ServiceOrder`) | _Em elaboração pelo grupo (Página 5 do PDF)_ |
| **Peça / Insumo** | Estoque (`src/Inventory`) | _Em elaboração pelo grupo (Página 9 do PDF)_ |
| **Serviço** | Catálogo (`src/Catalog`) | _Em elaboração pelo grupo (Página 14 do PDF)_ |

## Bounded Contexts

| Contexto | Pasta | Responsabilidade |
|---|---|---|
| Atendimento (OS) | `src/ServiceOrder` | Ciclo de vida da OS, orçamento, aprovação, status |
| Clientes e Veículos | `src/Customer` | Cadastro de clientes (CPF/CNPJ) e veículos (Placa, marca, modelo, ano) |
| Estoque | `src/Inventory` | Peças, insumos, saldo, reserva e baixa |
| Catálogo | `src/Catalog` | Serviços oferecidos e preço base |
| Identidade | `src/Identity` | Usuários administrativos e autenticação JWT |
| Shared Kernel | `src/Shared` | Value Objects comuns (`Document`, `LicensePlate`) e exceções de domínio |

## Camadas (por contexto)

```text
src/<Contexto>/
├── Domain/          # Entidades, VOs, agregados, eventos, interfaces de repositório. ZERO dependência do Laravel.
├── Application/     # Casos de uso (commands/handlers), DTOs, orquestração de transações.
├── Infrastructure/  # Eloquent, repositórios concretos, integrações externas.
└── Interfaces/      # Controllers HTTP, Form Requests, Resources, rotas.
```

Regra de dependência: `Interfaces → Application → Domain ← Infrastructure`.
O domínio não conhece o framework — é isso que garante testes unitários rápidos e a cobertura mínima de 80%.
