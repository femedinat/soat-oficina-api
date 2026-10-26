# Documentação DDD

| Artefato                                                        | Arquivo                                      | Link externo   |
| --------------------------------------------------------------- | -------------------------------------------- | -------------- |
| Event Storming (criação da OS, acompanhamento, peças e insumos) | `event-storming.pdf`                         | _link do Miro_ |
| Diagramas (context map, agregados)                              | `diagramas.pdf`                              | _link do Miro_ |
| Linguagem Ubíqua e glossário                                    | [`linguagem-ubiqua.md`](linguagem-ubiqua.md) | —              |

> ⚠️ O board do Miro precisa estar com acesso "qualquer pessoa com o link pode ver".

---

## 1. Bounded Contexts

| Contexto            | Pasta              | Responsabilidade                                     |
| ------------------- | ------------------ | ---------------------------------------------------- |
| Atendimento (OS)    | `src/ServiceOrder` | Ciclo de vida da OS, orçamento, aprovação, status    |
| Clientes e Veículos | `src/Customer`     | Cadastro de clientes (CPF/CNPJ) e veículos           |
| Estoque             | `src/Inventory`    | Peças, insumos, saldo, reserva e baixa               |
| Catálogo            | `src/Catalog`      | Serviços oferecidos e preço base                     |
| Identidade          | `src/Identity`     | Usuários administrativos e autenticação JWT          |
| Shared Kernel       | `src/Shared`       | Value Objects comuns (Document, LicensePlate, Money) |

---

## 2. Camadas e Arquitetura Limpa (por contexto)

```
src/<Contexto>/
├── Domain/          # Entidades, VOs, agregados, eventos, interfaces de repositório. ZERO dependência do Laravel.
├── Application/     # Casos de uso (commands/handlers), DTOs, orquestração de transações.
├── Infrastructure/  # Eloquent, repositórios concretos, integrações externas.
└── Interfaces/      # Controllers HTTP, Form Requests, Resources, rotas.
```

Regra de dependência: `Interfaces → Application → Domain ← Infrastructure`.
O domínio não conhece o framework — garantindo testes unitários rápidos e cobertura isolada.

---

## 3. Detalhamento dos Bounded Contexts Implementados

### 3.1. Shared Kernel (`src/Shared/`)
Contém conceitos e estruturas compartilhadas entre diferentes contextos:
- **`Document` & `DocumentType`** (`src/Shared/Domain/ValueObject/`):
  - Value Object imutável que encapsula e valida CPF (11 dígitos com cálculo de DVs) e CNPJ (14 dígitos).
  - Suporte à especificação do **CNPJ alfanumérico** (IN RFB nº 2.229/2024).
  - Métodos utilitários de formatação e mascaramento (`masked()`) para conformidade LGPD.
- **`Email`** (`src/Shared/Domain/ValueObject/Email.php`):
  - Value Object que normaliza o endereço em minúsculas, remove espaços e valida formato conforme RFCs.
- **`LicensePlate`** (`src/Shared/Domain/ValueObject/LicensePlate.php`):
  - Value Object para placas de veículos no padrão tradicional (cinza) e Mercosul.
- **Exceções de Domínio Base**:
  - `DomainException`: base para erros de negócio.
  - `InvalidValueException`: valores semanticamente inválidos (HTTP 422).
  - `NotFoundException`: recurso inexistente (HTTP 404).
  - `ConflictException`: duplicidade de chaves de negócio (HTTP 409).
- **`Page`** (`src/Shared/Application/Page.php`):
  - Estrutura tipada de paginação agnóstica de framework para tráfego entre Aplicação e Interfaces.

### 3.2. Bounded Context: Clientes (`src/Customer/`)
Responsável pelo ciclo de vida das pessoas físicas e jurídicas atendidas pela oficina:
- **Domínio (`src/Customer/Domain/`)**:
  - **`Customer`**: Agregado raiz que encapsula identidade (`CustomerId`), nome, documento (`Document`), e-mail (`Email`) e telefone opcional (`Phone`). Protege invariantes como tamanho de nome e higienização.
  - **`CustomerId`**: Identidade do agregado baseada em UUID v4 gerado no domínio.
  - **`Phone`**: Value Object para telefones brasileiros com DDD (10 ou 11 dígitos numéricos).
  - **`CustomerRepository`**: Interface com operações de persistência e consulta (`save`, `findById`, `findByDocument`, `delete`, `paginate`).
- **Aplicação (`src/Customer/Application/`)**:
  - **`RegisterCustomer`**: Valida a unicidade de documento, instancia a entidade e persiste.
  - **`UpdateCustomer`**: Atualiza dados garantindo que novo documento não colida com outro cliente.
  - **`FindCustomer`**: Consulta por ID com conversão de `CustomerId`.
  - **`ListCustomers`**: Listagem paginada com suporte a filtro por documento CPF/CNPJ e limite máximo de 100 itens.
  - **`DeleteCustomer`**: Exclusão lógica (*soft delete*) preservando histórico de veículos e ordens de serviço.
  - **`CustomerInput` & `CustomerView`**: DTOs desacoplados dos contratos HTTP.
- **Infraestrutura (`src/Customer/Infrastructure/`)**:
  - **`CustomerModel`**: Model Eloquent mapeando a tabela `customers` com trait `SoftDeletes`.
  - **`EloquentCustomerRepository`**: Implementação concreta da interface `CustomerRepository`. Trata `UniqueConstraintViolationException` para resguardar concorrência.
- **Interfaces (`src/Customer/Interfaces/Http/`)**:
  - **`CustomerController`**: Controller RESTful fino.
  - **`CustomerRequest`**: FormRequest para validação sintática do payload HTTP.
  - **`CustomerResource`**: Formatação padronizada JSON da resposta.

### 3.3. Bounded Context: Identidade e Acesso (`src/Identity/`)
Responsável pela segurança, controle de acesso e usuários do sistema:
- **Domínio (`src/Identity/Domain/`)**:
  - **`Role`**: Enum tipado com os papéis:
    - `ADMINISTRATOR` (`administrator`): Acesso total às operações administrativas.
    - `MECHANIC` (`mechanic`): Acesso operacional aos serviços da oficina e consulta de clientes.
    - `CUSTOMER` (`customer`): Acesso restrito a acompanhamento.
    - Regra de negócio: somente usuários com perfil `customer` possuem vínculo com cadastro de `Cliente`.
  - **`SystemUser`**: Agregado raiz representando a conta de acesso. Valida senha de no mínimo 8 caracteres e coerência entre perfil e `customerId`.
  - **`SystemUserRepository`**: Interface de persistência (`findByEmail`, `existsForCustomer`, `add`).
  - **`CustomerDirectory`**: Porta anticorrupção (ACL) para verificar existência de cliente sem acoplamento direto.
  - **`PasswordHasher`**: Porta para cálculo de hashes Bcrypt, com operação `burn()` preventiva contra *timing attacks*.
- **Aplicação (`src/Identity/Application/`)**:
  - **`AuthenticateSystemUser`**: Autenticação com credenciais e emissão de token JWT via `TokenIssuer`.
  - **`CreateUser`**: Cadastro de novos usuários, validando e-mail único, cliente existente e relação 1:1.
- **Infraestrutura (`src/Identity/Infrastructure/`)**:
  - **`EloquentSystemUserRepository`**: Persistência sobre `App\Models\User`.
  - **`EloquentCustomerDirectory`**: Consulta da existência na tabela `customers`.
  - **`LaravelPasswordHasher`**: Implementação via `Hash` do Laravel.
  - **`JwtTokenIssuer`**: Emissão do token usando `php-open-source-saver/jwt-auth`.
- **Interfaces (`src/Identity/Interfaces/Http/`)**:
  - **`AuthController`**: `login`, `me`, `refresh` e `logout`.
  - **`UserController`**: Criação de usuários restrita a administradores.
  - **`RequireRole`**: Middleware HTTP para autorização baseada em perfil (`role:administrator`, `role:administrator,mechanic`).

---

## 4. Modelagem Relacional e Integridade (PostgreSQL)

### 4.1. Tabela `customers`
- `id` (UUID, Primary Key): Identificador único gerado no domínio.
- `name` (VARCHAR 150): Nome completo ou razão social.
- `document` (VARCHAR 14, UNIQUE): CPF ou CNPJ normalizado.
- `email` (VARCHAR 254): E-mail normalizado.
- `phone` (VARCHAR 11, NULLABLE): Telefone contendo apenas dígitos (DDD + número).
- `created_at`, `updated_at`, `deleted_at`: Timestamps padrão com suporte a *Soft Delete*.

### 4.2. Tabela `users`
- `role` (VARCHAR 20, DEFAULT 'administrator'): Perfil de acesso (`administrator`, `mechanic`, `customer`).
- `customer_id` (UUID, NULLABLE, UNIQUE): Chave estrangeira referenciando `customers(id)`.
- **Integridade Garantida por Constraint no Banco**:
  ```sql
  ALTER TABLE users ADD CONSTRAINT users_role_customer_check CHECK (
      role IN ('administrator', 'mechanic', 'customer')
      AND ((role = 'customer') = (customer_id IS NOT NULL))
  );
  ```
  Esta constraint garante no nível do PostgreSQL que:
  1. Usuários `customer` obrigatoriamente apontam para um `customer_id`.
  2. Usuários `administrator` e `mechanic` nunca possuem `customer_id`.
  3. O modificador `UNIQUE` em `customer_id` garante a relação 1:1.
