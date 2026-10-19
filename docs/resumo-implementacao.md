# Resumo da Implementação: Autenticação JWT, CRUD de Clientes e Gestão de Usuários

Este documento consolida e detalha todas as decisões arquiteturais, técnicas e de implementação realizadas para a entrega do primeiro incremento funcional da **Oficina API**, contemplando:
- **Autenticação JWT** e controle de acesso baseado em perfis (RBAC).
- **CRUD de Clientes** com validação estrita de dados sensíveis (CPF e CNPJ).
- **Gestão de Usuários** com distinção de papéis (`Administrador`, `Mecânico`, `Cliente`) e relacionamento **1 para 1** com `Cliente`.
- **Testes automatizados e cobertura** (> 98% sobre o código de domínio/aplicação).

---

## 1. Visão Geral e Filosofia Arquitetural

O projeto foi construído como um monólito modular orientado a **Domain-Driven Design (DDD)**. Toda regra de negócio reside exclusivamente na camada de Domínio, em PHP puro (sem referências ao framework Laravel, facades ou Eloquent).

A estrutura de diretórios respeita a regra de dependência estrita:
```
Interfaces (HTTP / Controllers / Requests)
    │
    ▼
Application (Casos de Uso / DTOs / Projeções)
    │
    ▼
Domain (Agregados / Entidades / Value Objects / Interfaces de Repositório / Exceções)
    ▲
    │
Infrastructure (Repositórios Eloquent / Migrations / JWT / Hashers)
```

---

## 2. Bounded Contexts e Estrutura DDD

### 2.1. Shared Kernel (`src/Shared/`)
Contém conceitos e estruturas compartilhadas entre diferentes contextos:
- **`Document` & `DocumentType`** (`src/Shared/Domain/ValueObject/`):
  - Value Object imutável que encapsula e valida CPF (11 dígitos com cálculo de DVs) e CNPJ (14 dígitos).
  - Suporte à especificação do **CNPJ alfanumérico** (IN RFB nº 2.229/2024).
  - Métodos utilitários de formatação e mascaramento (`masked()`) para proteção de dados sensíveis (LGPD).
- **`Email`** (`src/Shared/Domain/ValueObject/Email.php`):
  - Value Object que normaliza o endereço em letras minúsculas, remove espaços e valida formato conforme RFCs.
- **Exceções de Domínio Base**:
  - `DomainException`: base para erros de negócio.
  - `InvalidValueException`: valores semanticamente inválidos (mapeado para HTTP 422).
  - `NotFoundException`: recurso inexistente (mapeado para HTTP 404).
  - `ConflictException`: conflito de estado, como duplicidade de chaves de negócio (mapeado para HTTP 409).
- **`Page`** (`src/Shared/Application/Page.php`):
  - Estrutura tipada de paginação agnóstica de framework para tráfego entre Aplicação e Interfaces.

---

### 2.2. Bounded Context: Clientes (`src/Customer/`)
Responsável pelo ciclo de vida das pessoas físicas e jurídicas atendidas pela oficina mecânica.

- **Domínio (`src/Customer/Domain/`)**:
  - **`Customer`**: Agregado raiz que encapsula o identificador (`CustomerId`), nome, documento (`Document`), e-mail (`Email`) e telefone opcional (`Phone`). Protege invariantes como tamanho máximo do nome (150 caracteres) e higienização de espaços duplicados.
  - **`CustomerId`**: Identidade do agregado baseada em UUID v4 gerado no domínio.
  - **`Phone`**: Value Object que valida telefones brasileiros com DDD (10 ou 11 dígitos numéricos).
  - **`CustomerRepository`**: Interface com as operações de persistência e consulta (`save`, `findById`, `findByDocument`, `delete`, `paginate`).
- **Aplicação (`src/Customer/Application/`)**:
  - **`RegisterCustomer`**: Valida a unicidade do CPF/CNPJ, instancia a entidade e a persiste.
  - **`UpdateCustomer`**: Atualiza dados do cliente garantindo que novo CPF/CNPJ não colida com outro cliente.
  - **`FindCustomer`**: Consulta por ID com conversão de `CustomerId`.
  - **`ListCustomers`**: Paginação ordenada com limite defensivo de no máximo 100 itens por página.
  - **`DeleteCustomer`**: Remoção do cliente.
  - **`CustomerInput` & `CustomerView`**: DTOs desacoplados dos contratos HTTP.
- **Infraestrutura (`src/Customer/Infrastructure/`)**:
  - **`CustomerModel`**: Model Eloquent mapeando a tabela `customers`.
  - **`EloquentCustomerRepository`**: Implementação concreta da interface `CustomerRepository`. Trata `UniqueConstraintViolationException` para resguardar concorrência.
  - **`CustomerServiceProvider`**: Registra as injeções de dependência no container do Laravel.
- **Interfaces (`src/Customer/Interfaces/Http/`)**:
  - **`CustomerController`**: Controller RESTful fino. Apenas orquestra entrada HTTP -> Caso de Uso -> Resource.
  - **`CustomerRequest`**: FormRequest para validação sintática do payload HTTP.
  - **`CustomerResource`**: Formatação padronizada JSON da resposta.

---

### 2.3. Bounded Context: Identidade e Acesso (`src/Identity/`)
Responsável pela segurança, controle de acesso e usuários do sistema.

- **Domínio (`src/Identity/Domain/`)**:
  - **`Role`**: Enum tipado com os papéis:
    - `ADMINISTRATOR` (`administrator`): Acesso total às operações administrativas.
    - `MECHANIC` (`mechanic`): Acesso operacional aos serviços da oficina.
    - `CUSTOMER` (`customer`): Acesso específico a clientes (portal/acompanhamento).
    - Método `requiresCustomer()`: expressa a regra de que somente o perfil `customer` deve possuir vínculo com `Cliente`.
  - **`SystemUser`**: Agregado raiz que representa o usuário no sistema. Exige senha com no mínimo 8 caracteres, valida a coerência entre o perfil e a presença de `customerId` e delega checagem de hash para a porta `PasswordHasher`.
  - **`SystemUserRepository`**: Interface de persistência (`findByEmail`, `existsForCustomer`, `add`).
  - **`CustomerDirectory`**: Porta anticorrupção (ACL) para que o contexto de Identidade verifique a existência de um cliente sem acoplar diretamente o domínio `Customer`.
  - **`PasswordHasher`**: Porta para cálculo e validação de hashes Bcrypt, contendo inclusive a operação defensiva `burn()` (executa hash de descarte para evitar *timing attacks* caso o e-mail não exista).
- **Aplicação (`src/Identity/Application/`)**:
  - **`AuthenticateSystemUser`**: Caso de uso de login. Busca usuário por e-mail (insensível a maiúsculas/minúsculas), valida a senha e emite o token JWT via `TokenIssuer`.
  - **`CreateUser`**: Caso de uso de cadastro de novos usuários. Garante e-mail único, existência do cliente informado e a relação exclusiva 1:1.
  - **`AccessToken` & `UserView`**: DTOs de transporte da camada de aplicação.
- **Infraestrutura (`src/Identity/Infrastructure/`)**:
  - **`EloquentSystemUserRepository`**: Persistência sobre o model `App\Models\User`.
  - **`EloquentCustomerDirectory`**: Implementação da porta consultando a existência na tabela `customers`.
  - **`LaravelPasswordHasher`**: Implementação da porta usando o `Hash` nativo do Laravel.
  - **`JwtTokenIssuer`**: Emissão do token usando a biblioteca `php-open-source-saver/jwt-auth`.
  - **`IdentityServiceProvider`**: Registro de bindings no container de injeção de dependências.
- **Interfaces (`src/Identity/Interfaces/Http/`)**:
  - **`AuthController`**:
    - `POST /api/auth/login`: Autenticação e retorno de Bearer token.
    - `GET /api/auth/me`: Retorna os dados do usuário autenticado (incluindo seu `role` e `customer_id`).
    - `POST /api/auth/logout`: Invalidação imediata do token JWT (blacklist).
  - **`UserController`**:
    - `POST /api/users`: Endpoint restrito a administradores para criação de novos usuários com definição de perfil e vínculo de cliente.
  - **`RequireRole`**: Middleware HTTP que restringe o acesso de rotas a perfis específicos (ex.: `role:administrator`).

---

## 3. Banco de Dados e Modelagem Relacional (PostgreSQL)

### 3.1. Tabela `customers` (`database/migrations/2026_10_18_200000_create_customers_table.php`)
- `id` (UUID, Primary Key): Identificador único do cliente gerado no domínio.
- `name` (VARCHAR 150): Nome completo ou razão social.
- `document` (VARCHAR 14, UNIQUE): CPF ou CNPJ armazenado de forma normalizada (apenas caracteres alfanuméricos em caixa alta).
- `email` (VARCHAR 254): E-mail normalizado.
- `phone` (VARCHAR 11, NULLABLE): Telefone contendo apenas dígitos (DDD + número).
- `created_at`, `updated_at`: Timestamps padrão.

### 3.2. Tabela `users` (`database/migrations/2026_10_18_230000_add_role_and_customer_to_users_table.php`)
- `role` (VARCHAR 20, DEFAULT 'administrator'): Perfil de acesso (`administrator`, `mechanic`, `customer`).
- `customer_id` (UUID, NULLABLE, UNIQUE): Chave estrangeira referenciando `customers(id)` com integridade referencial `ON DELETE CASCADE`.
- **Integridade Garantida por Constraint no Banco**:
  ```sql
  ALTER TABLE users ADD CONSTRAINT users_role_customer_check CHECK (
      role IN ('administrator', 'mechanic', 'customer')
      AND ((role = 'customer') = (customer_id IS NOT NULL))
  );
  ```
  Esta restrição garante matematicamente no PostgreSQL que:
  1. Nenhum usuário com perfil `customer` seja criado sem apontar para um `customer_id`.
  2. Nenhum usuário `administrator` ou `mechanic` aponte para um `customer_id`.
  3. A coluna `customer_id` com modificador `UNIQUE` impõe a relação **1 para 1** (um cliente só pode ter no máximo uma conta de usuário).

---

## 4. Integração HTTP e Exceções Globais

No arquivo [`bootstrap/app.php`](file:///home/raniery/projetos/18SOAT/soat-oficina-api/bootstrap/app.php):
- Registrou-se o alias de middleware `'role' => RequireRole::class`.
- O framework traduz de forma desacoplada as exceções do domínio para os status HTTP corretos:
  - `InvalidCredentialsException` -> **HTTP 401 Unauthorized**
  - `NotFoundException` -> **HTTP 404 Not Found**
  - `ConflictException` (e-mail duplicado, documento duplicado, cliente já com usuário) -> **HTTP 409 Conflict**
  - `DomainException` e `InvalidValueException` -> **HTTP 422 Unprocessable Content**

---

## 5. Endpoints da API RESTful

| Método | Endpoint | Proteção | Descrição |
|---|---|---|---|
| `POST` | `/api/auth/login` | Público (`throttle:10,1`) | Realiza login e retorna token JWT |
| `GET` | `/api/auth/me` | `auth:api` | Retorna dados e perfil do usuário logado |
| `POST` | `/api/auth/logout` | `auth:api` | Invalida o token JWT |
| `POST` | `/api/users` | `auth:api`, `role:administrator` | Cria usuário com papel (`role`) e vínculo de cliente opcional/obrigatório |
| `GET` | `/api/customers` | `auth:api` | Lista clientes paginados |
| `POST` | `/api/customers` | `auth:api` | Cadastra novo cliente (valida CPF/CNPJ) |
| `GET` | `/api/customers/{customer}` | `auth:api` | Consulta cliente por ID |
| `PUT` | `/api/customers/{customer}` | `auth:api` | Atualiza dados do cliente |
| `DELETE` | `/api/customers/{customer}` | `auth:api` | Exclui cliente |

A documentação interativa OpenAPI 3.1 é servida nativamente pelo Scramble em `/docs/api` com suporte completo à autorização via Bearer JWT.

---

## 6. Testes Automatizados e Qualidade de Software

### 6.1. Testes Unitários
Construídos em `tests/Unit/` utilizando dublês de teste (*Test Doubles*) em memória (`InMemoryCustomerRepository`, `InMemorySystemUserRepository`, `FakePasswordHasher`), garantindo execução extremamente rápida:
- **`CustomerTest`** e **`CustomerUseCasesTest`**: Validam cadastro, regras de negócio de CPF/CNPJ, unicidade, higienização, limites de paginação e exceções.
- **`CreateUserTest`** e **`AuthenticateSystemUserTest`**: Cobrem cada perfil de usuário, senhas curtas, timing attack, unicidade e regras da relação 1:1.
- **`DocumentTest`**, **`EmailTest`**, **`LicensePlateTest`**: Testam exaustivamente os Value Objects compartilhados com *Data Providers*.

### 6.2. Testes de Integração
Localizados em `tests/Integration/`:
- **`AuthApiTest`**: Fluxo HTTP completo de login, rate limit, emissão de token JWT válido, consumo de rotas protegidas e logout com blacklist.
- **`CustomerApiTest`**: Chamadas reais à API REST de clientes com banco PostgreSQL transacional (`RefreshDatabase`).
- **`UserApiTest`**: Valida a permissão de acesso por perfil (bloqueio 403 para não-admins), criação de mecânico e cliente, e testes de integridade relacional no banco.

### 6.3. Cobertura e Análise Estática
- **Cobertura de Código**: **98.5%** sobre toda a pasta `src/` (muito acima do requisito obrigatório de 80%).
- **PHPStan / Larastan**: Nível 6 executado com sucesso e zero erros (`[OK] No errors`).
- **Laravel Pint**: Todas as regras PSR-12 / Laravel Code Style validadas e em conformidade.
- **Composer Audit**: Nenhuma vulnerabilidade conhecida encontrada nas dependências.

---

## 7. Termos Técnicos para a Linguagem Ubíqua (`docs/ddd/linguagem-ubiqua.md`)

Sugestão de termos documentados a serem adicionados ao glossário do projeto:

| Termo | Contexto | Definição |
|---|---|---|
| **Usuário do Sistema (`SystemUser`)** | Identidade | Conta de acesso que pode autenticar via credenciais na API. |
| **Perfil de Acesso (`Role`)** | Identidade | Nível de permissão do usuário: `Administrador`, `Mecânico` ou `Cliente`. |
| **Vínculo Usuário-Cliente** | Identidade / Clientes | Relação de um para um (1:1) que associa uma conta de usuário do tipo `Cliente` ao seu registro correspondente de `Cliente`. |
| **Token de Acesso (JWT)** | Identidade | Credencial transitória de autenticação enviada no cabeçalho `Authorization: Bearer`. |
| **Cliente (`Customer`)** | Clientes | Agregado raiz representando a pessoa física ou jurídica proprietária dos veículos atendidos na oficina. |
| **Documento (`Document`)** | Shared Kernel | Value Object de identificação fiscal obrigatória: CPF ou CNPJ (incluindo formato alfanumérico). |
| **Telefone (`Phone`)** | Clientes | Value Object representando o número de contato com DDD válido. |
