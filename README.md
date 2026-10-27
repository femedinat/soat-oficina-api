# 🔧 Oficina API — Sistema Integrado de Atendimento e Execução de Serviços

> Tech Challenge — **SOAT (Software Architecture) · Fase 1** · Grupo `XX`

![CI](https://github.com/OWNER/REPO/actions/workflows/ci.yml/badge.svg)

## Objetivo
MVP do back-end de uma oficina mecânica de médio porte: gestão de **ordens de serviço**, **clientes**,
**veículos**, **serviços** e **peças/insumos com controle de estoque**, com acompanhamento de status
pelo cliente e aprovação de orçamento via API.

## Solução
API RESTful construída em **PHP 8.4** e **Laravel 13** utilizando arquitetura em camadas orientada a **Domain-Driven Design (DDD)**. O sistema separa estritamente as regras de negócio puras (`Domain`) da orquestração de casos de uso (`Application`), persistência transacional em PostgreSQL (`Infrastructure`) e exposição HTTP/OpenAPI (`Interfaces`), garantindo alta testabilidade, validações de domínio ricas (CPF/CNPJ, placas padrão antigo e Mercosul, ciclo de vida de veículos e ordens de serviço) e documentação interativa gerada automaticamente via Scramble.

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
- **Docker 24+** e **Docker Compose v2**
- Opcional: `make` (nativo no Linux/macOS ou via **WSL2** no Windows)
- Opcional (para rodar fora do container): **PHP 8.4+** (com extensão `pdo_pgsql`) e **Composer 2+** (ex.: Laravel Herd)

## Execução local (Passo a Passo)

### Opção 1 — Linux, macOS ou Windows (WSL2) com `make`
```bash
git clone https://github.com/OWNER/REPO.git && cd soat-oficina-api
make setup      # copia .env, sobe containers, instala deps, gera chaves, migra e popula o banco
```
API: http://localhost:8080 · `make help` lista todos os comandos disponíveis.

### Opção 2 — Windows (PowerShell) com Docker Compose (sem `make`)
```powershell
# 1. Criar o arquivo .env a partir do exemplo
Copy-Item .env.example .env

# 2. Construir e subir os containers (app, web e db)
docker compose up -d --build

# 3. Instalar dependências, gerar chaves e rodar as migrations
docker compose exec app composer install --no-interaction
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret --force
docker compose exec app php artisan migrate --seed
```
API disponível em: http://localhost:8080

### Opção 3 — PHP Local (ex.: Laravel Herd) + PostgreSQL no Docker
Se preferir rodar comandos `php artisan` diretamente no terminal do sistema operacional usando apenas o banco de dados no Docker:

1. Copie o `.env.example` para `.env` e ajuste o `DB_HOST` de `db` para `127.0.0.1`:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=oficina
   DB_USERNAME=oficina
   DB_PASSWORD=oficina
   ```
2. Suba o container do PostgreSQL (ele já cria automaticamente os bancos `oficina` e `oficina_test`):
   ```powershell
   docker compose up -d db
   ```
3. Instale as dependências, gere as chaves e execute as migrations localmente:
   ```powershell
   composer install
   php artisan key:generate
   php artisan jwt:secret --force
   php artisan migrate --seed
   ```
4. Suba o servidor local:
   ```powershell
   php artisan serve --port=8080
   ```

## Testes e Qualidade de Código

### Com `make` (Linux/macOS/WSL2)
```bash
make test       # unitários + integração + feature
make coverage   # com gate de cobertura mínima de 80% sobre src/
make check      # tudo que o CI roda (lint + stan + coverage + audit)
```

### Sem `make` (Docker Compose / PowerShell)
```powershell
# Rodar suíte de testes
docker compose exec app php artisan test

# Rodar testes com cobertura mínima de 80% (via PCOV no container)
docker compose exec app php artisan test --coverage --min=80

# Verificar estilo de código (Laravel Pint) e análise estática (Larastan)
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```

### Com PHP Local (com `DB_HOST=127.0.0.1` no `.env` e container `db` rodando)
```powershell
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
```

## Documentação da API
- Swagger UI: http://localhost:8080/docs/api
- OpenAPI JSON: http://localhost:8080/docs/api.json

### Endpoints — Veículos (`/api/vehicles`)
| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/vehicles` | Lista veículos (filtros opcionais: `customer_id`, `only_active`) |
| `POST` | `/api/vehicles` | Cadastra um novo veículo (valida placa antiga/Mercosul e unicidade) |
| `GET` | `/api/vehicles/{id}` | Consulta veículo por UUID |
| `GET` | `/api/vehicles/plate/{plate}` | Consulta veículo por placa |
| `PUT` | `/api/vehicles/{id}` | Atualiza dados cadastrais do veículo |
| `PATCH` | `/api/vehicles/{id}/mileage` | Registra evolução de quilometragem do veículo |
| `PATCH` | `/api/vehicles/{id}/transfer` | Transfere propriedade do veículo para outro cliente |
| `PATCH` | `/api/vehicles/{id}/activate` | Reativa um veículo inativo |
| `DELETE` | `/api/vehicles/{id}` | Inativa um veículo preservando seu histórico |

## Banco de dados: por que PostgreSQL
Domínio transacional com reserva/baixa de estoque concorrente → ACID, `SELECT ... FOR UPDATE`,
CHECK constraints e integridade referencial. Decisão completa: [ADR 0001](docs/adr/0001-banco-de-dados-postgresql.md).

## Autenticação e usuários de demonstração
| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | `admin@oficina.local` | `admin-local-123` (configurada via `ADMIN_SEED_PASSWORD` no `.env`) |

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@oficina.local","password":"admin-local-123"}'
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
