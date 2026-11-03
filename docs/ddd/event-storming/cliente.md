# Event Storming — Agregado: Cliente

> **Ciclo de vida do cliente**: cadastro inicial, atualização de dados e contato, consulta e inativação (Página 6 de [`../event-storming.pdf`](../event-storming.pdf)).

---

## 1. Visão Geral do Agregado

- **Agregado**: `Cliente` ([`Oficina\Customer\Domain\Customer`](../../../src/Customer/Domain/Customer.php))
- **Bounded Context**: Contexto Administrativo — Clientes e Veículos (`src/Customer`)
- **Objetos de Valor Principais**:
  - `Documento` ([`Oficina\Shared\Domain\ValueObject\Document`](../../../src/Shared/Domain/ValueObject/Document.php)) — valida CPF (11 dígitos) e CNPJ (14 caracteres, numérico ou alfanumérico conforme IN RFB nº 2.229/2024) com cálculo dos dígitos verificadores.
  - `Email` ([`Oficina\Shared\Domain\ValueObject\Email`](../../../src/Shared/Domain/ValueObject/Email.php)) — normaliza e valida endereços de e-mail.
  - `Telefone` ([`Oficina\Customer\Domain\Phone`](../../../src/Customer/Domain/Phone.php)) — valida telefones brasileiros com DDD (10 ou 11 dígitos).
  - `CustomerId` ([`Oficina\Customer\Domain\CustomerId`](../../../src/Customer/Domain/CustomerId.php)) — identificador único UUID v4 do agregado.
- **Definição na Linguagem Ubíqua**: *"Pessoa física ou jurídica que contrata os serviços, identificada unicamente e validada pelo seu CPF ou CNPJ."*

---

## 2. Fases do Event Storming (Mapeamento Completo)

```mermaid
flowchart TD
    subgraph F1["FASE 1 — CADASTRO INICIAL"]
        direction LR
        RM1["Modelo de Leitura: Formulário de Cadastro de Cliente"] --> A1["Ator: Atendente"]
        A1 --> C1["Comando: Cadastrar Novo Cliente"]
        C1 --> P1["Política: Validar CPF/CNPJ"]
        P1 --> EV1["Evento: CPF/CNPJ Validado"]
        EV1 --> P2["Política: Verificar Duplicidade de CPF/CNPJ"]
        P2 -->|Documento válido e único| EV2["Evento: Cliente Validado / Cliente Cadastrado"]
        P2 -->|Documento já existente| EV3["Evento: Cliente Duplicado (Erro 409)"]
    end

    subgraph F2["FASE 2 — ATUALIZAÇÃO DE DADOS"]
        direction LR
        RM2["Modelo de Leitura: Perfil do Cliente"] --> A2["Ator: Atendente"]
        A2 --> C2["Comando: Atualizar Dados do Cliente"]
        C2 --> EV4["Evento: Dados do Cliente Atualizados"]

        RM3["Modelo de Leitura: Perfil do Cliente"] --> A3["Ator: Atendente"]
        A3 --> C3["Comando: Atualizar Contato (telefone/email)"]
        C3 --> EV5["Evento: Contato Atualizado"]
        EV5 --> P3["Política: Enviar Confirmação de Atualização"]
        P3 --> EXT1["Sistema Externo: Sistema de envio de e-mail"]
        EXT1 --> EV6["Evento: Confirmação Enviada"]
    end

    subgraph F3["FASE 3 — CONSULTA"]
        direction LR
        RM4["Modelo de Leitura: Histórico / Perfil do Cliente"] --> A4["Ator: Atendente"]
        A4 --> C4["Query: Consultar Dados do Cliente por ID, CPF/CNPJ ou Listagem"]
        C4 --> N1["Fluxo apenas de leitura — não altera o agregado"]
    end

    subgraph F4["FASE 4 — INATIVAÇÃO E REATIVAÇÃO"]
        direction LR
        RM5["Modelo de Leitura: Perfil do Cliente"] --> A5["Ator: Gerente"]
        A5 --> C5["Comando: Inativar Cliente"]
        C5 --> P4["Política: Arquivar Histórico de Serviços"]
        P4 --> EV7["Evento: Cliente Inativado"]

        RM6["Modelo de Leitura: Perfil do Cliente Inativo"] --> A6["Ator: Gerente"]
        A6 --> C6["Comando: Reativar Cliente"]
        C6 --> EV8["Evento: Cliente Reativado"]
    end

    F1 ~~~ F2 ~~~ F3 ~~~ F4
```

---

## 3. Regras de Negócio e Invariantes do Agregado

1. **Validação Obrigatória de CPF/CNPJ**:
   - Todo documento é normalizado (remoção de pontuação e conversão para maiúsculas) e validado matematicamente pelo Objeto de Valor [`Document`](../../../src/Shared/Domain/ValueObject/Document.php), suportando CPF (11 dígitos) e CNPJ (14 caracteres numéricos ou alfanuméricos).
2. **Unicidade de CPF/CNPJ no Sistema**:
   - Não é permitido cadastrar dois clientes com o mesmo CPF ou CNPJ ([`DocumentAlreadyRegisteredException`](../../../src/Customer/Domain/Exception/DocumentAlreadyRegisteredException.php) $\rightarrow$ HTTP `409 Conflict`).
3. **Dados Cadastrais e de Contato**:
   - `nome` (`name`) é obrigatório, higienizado contra espaços duplicados e limitado a `150` caracteres.
   - `email` é obrigatório, normalizado em letras minúsculas e validado pelo Objeto de Valor [`Email`](../../../src/Shared/Domain/ValueObject/Email.php).
   - `telefone` (`phone`) é opcional e, quando informado, deve conter DDD + número válido (10 ou 11 dígitos) validado pelo Objeto de Valor [`Phone`](../../../src/Customer/Domain/Phone.php).
4. **Atualização de Contato e Confirmação por E-mail**:
   - A atualização exclusiva de contato (`updateContact()`) altera `email` e `phone` do cliente ativo e aciona a política de envio de confirmação por e-mail através da porta [`CustomerContactNotifier`](../../../src/Customer/Domain/CustomerContactNotifier.php).
5. **Inativação Segura e Preservação de Histórico**:
   - A inativação (`deactivate()`) realiza exclusão lógica (`active = false`), preservando e arquivando o histórico de Ordens de Serviço e Veículos vinculados ao cliente.
   - Clientes inativos não permitem atualização de dados cadastrais ou de contato sem antes serem reativados (`activate()`).

---

## 4. Rastreabilidade: Documentação DDD $\leftrightarrow$ Implementação no Código

| Fase | Comando / Operação no Event Storming | Caso de Uso (`Application`) | Método no Agregado (`Domain`) | Endpoint REST (`Interfaces`) |
|---|---|---|---|---|
| **Fase 1** | Cadastrar Novo Cliente + Validar CPF/CNPJ + Verificar Duplicidade | [`RegisterCustomer`](../../../src/Customer/Application/RegisterCustomer.php) | `Customer::register(...)` + `Document::from(...)` | `POST /api/customers` |
| **Fase 2** | Atualizar Dados do Cliente | [`UpdateCustomer`](../../../src/Customer/Application/UpdateCustomer.php) | `$customer->revise(...)` | `PUT /api/customers/{customer}` |
| **Fase 2** | Atualizar Contato (telefone/email) + Enviar Confirmação | [`UpdateCustomerContact`](../../../src/Customer/Application/UpdateCustomerContact.php) | `$customer->updateContact(...)` + `CustomerContactNotifier` | `PATCH /api/customers/{customer}/contact` |
| **Fase 3** | Consultar Dados do Cliente por ID | [`FindCustomer`](../../../src/Customer/Application/FindCustomer.php) | `CustomerRepository::findById` | `GET /api/customers/{customer}` |
| **Fase 3** | Consultar Dados do Cliente por CPF/CNPJ | [`FindCustomerByDocument`](../../../src/Customer/Application/FindCustomerByDocument.php) | `CustomerRepository::findByDocument` | `GET /api/customers/document/{document}` |
| **Fase 3** | Listar Clientes (Paginado e com filtro de status) | [`ListCustomers`](../../../src/Customer/Application/ListCustomers.php) | `CustomerRepository::paginate` | `GET /api/customers` |
| **Fase 4** | Inativar Cliente + Arquivar Histórico de Serviços | [`DeactivateCustomer`](../../../src/Customer/Application/DeactivateCustomer.php) | `$customer->deactivate()` | `DELETE /api/customers/{customer}` |
| **Fase 4** | Reativar Cliente | [`ActivateCustomer`](../../../src/Customer/Application/ActivateCustomer.php) | `$customer->activate()` | `PATCH /api/customers/{customer}/activate` |
