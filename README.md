# 🔧 Oficina API — Sistema Integrado de Atendimento e Execução de Serviços

> Tech Challenge — **SOAT (Software Architecture) · Fase 1** · Grupo `XX`

![CI](https://github.com/OWNER/REPO/actions/workflows/ci.yml/badge.svg)

## Objetivo
MVP do back-end de uma oficina mecânica de médio porte: gestão de **ordens de serviço**, **clientes**,
**veículos**, **serviços** e **peças/insumos com controle de estoque**, com acompanhamento de status
pelo cliente e aprovação de orçamento via API.

## Solução
_Resumo da solução (preencher)._

## Arquitetura
Monólito modular em camadas orientado a **Domain-Driven Design**:

```
Interfaces (HTTP) → Application (casos de uso) → Domain (regras) ← Infrastructure (Eloquent/PostgreSQL)
```

Cada Bounded Context vive em `src/<Contexto>` com as quatro camadas. O domínio é PHP puro,
sem dependência do Laravel. Detalhes em [docs/ddd](docs/ddd/README.md).

## Tecnologias
| | |
|---|---|
| Linguagem | PHP 8.4 |
| Framework | Laravel 13 |
| Banco | PostgreSQL 17 |
| Autenticação | JWT (`php-open-source-saver/jwt-auth`) |
| Documentação da API | OpenAPI 3.1 gerado por Scramble |
| Testes | PHPUnit + PCOV |
| Qualidade | Laravel Pint, Larastan |
| Segurança | Trivy (deps, segredos, misconfig), `composer audit`, Dependabot, secret scanning |
| Infra | Docker, Docker Compose, Nginx, GitHub Actions |

## Pré-requisitos
- Docker 24+ e Docker Compose v2
- `make` (Linux/macOS nativo; no Windows use **WSL2**)
- Nenhum PHP/Composer local é necessário

## Execução local
```bash
git clone https://github.com/OWNER/REPO.git && cd REPO
make setup      # sobe containers, instala deps, gera chaves, migra e popula o banco
```
API: http://localhost:8080 · `make help` lista todos os comandos.

## Testes
```bash
make test       # unitários + integração
make coverage   # com gate de cobertura mínima de 80% sobre src/
make check      # tudo que o CI roda
```

## Documentação da API
- Swagger UI: http://localhost:8080/docs/api
- OpenAPI JSON: http://localhost:8080/docs/api.json

## Banco de dados: por que PostgreSQL
Domínio transacional com reserva/baixa de estoque concorrente → ACID, `SELECT ... FOR UPDATE`,
CHECK constraints e integridade referencial. Decisão completa: [ADR 0001](docs/adr/0001-banco-de-dados-postgresql.md).

## Autenticação e usuários de demonstração
| Perfil | E-mail | Senha |
|---|---|---|
| Admin | `admin@oficina.local` | _definida no seeder — apenas ambiente local_ |

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@oficina.local","password":"..."}'
# use o token retornado: Authorization: Bearer <token>
```
Endpoints de consulta do cliente (acompanhamento da OS) _(definir estratégia: código da OS + documento)_.

## Estrutura de diretórios
```
├── app/                    # Bootstrap do Laravel (providers, exceptions handler)
├── src/                    # ⭐ Código de domínio (namespace Oficina\)
│   ├── Shared/             # Shared Kernel: Value Objects comuns
│   ├── ServiceOrder/       # Contexto: Ordem de Serviço
│   ├── Customer/           # Contexto: Clientes e Veículos
│   ├── Inventory/          # Contexto: Peças, insumos e estoque
│   ├── Catalog/            # Contexto: Serviços
│   └── Identity/           # Contexto: Autenticação
├── tests/{Unit,Integration,Feature}
├── docker/                 # Dockerfile (multi-stage), nginx, init do Postgres
├── docs/
│   ├── ddd/                # Event Storming, diagramas, linguagem ubíqua
│   └── adr/                # Architecture Decision Records
└── .github/                # CI, Dependabot, templates de PR/issue
```

## Análise de vulnerabilidades
Executada em todo PR pelo job **Análise de vulnerabilidades** (Trivy + `composer audit`).
O relatório fica disponível como artefato `security-report` na execução do workflow.
_Evidência da versão entregue: `docs/security/` (anexar)._

## Links da entrega
- 📐 Documentação DDD (Miro): _link_
- 🎥 Vídeo: _link_
- 🏷️ Versão avaliada: branch `main`, tag `v1.0.0-fase1`, commit `_hash_`

## Integrantes
| Nome | RM | Discord |
|---|---|---|
| | | |
