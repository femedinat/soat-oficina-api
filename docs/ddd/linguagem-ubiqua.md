# Linguagem Ubíqua e Glossário do Domínio

Este documento consolida a **Linguagem Ubíqua** utilizada pela equipe e pelos especialistas de domínio no **Sistema Integrado de Atendimento e Execução de Serviços**, bem como a relação direta entre os termos do negócio e sua implementação no código-fonte.

---

## 1. Glossário de Termos do Domínio

Conforme mapeado na documentação DDD do projeto (Página 8 de [`event-storming.pdf`](event-storming.pdf)):

| # | Termo | Definição do Negócio (Glossário) | Contexto Delimitado (Bounded Context) | Representação no Código (`Oficina\`) |
|---|---|---|---|---|
| 1 | **Ordem de Serviço (OS)** | Documento central que acompanha todo o ciclo de vida do atendimento, registrando o veículo, o cliente, os itens adicionados e o orçamento gerado. | Contexto de Atendimento (*Core Domain*) | `Oficina\ServiceOrder\Domain\...` |
| 2 | **Cliente** | Pessoa física ou jurídica que contrata os serviços, identificada unicamente e validada pelo seu CPF ou CNPJ. | Contexto Administrativo (*Generic Domain*) | `Oficina\Customer\Domain\...` + VO [`Document`](../../src/Shared/Domain/ValueObject/Document.php) |
| 3 | **Veículo** | O automóvel do cliente que passará pela manutenção, identificado unicamente por sua placa, marca, modelo e ano. | Contexto Administrativo (*Generic Domain*) | [`Vehicle`](../../src/Customer/Domain/Entity/Vehicle.php) + VO [`LicensePlate`](../../src/Shared/Domain/ValueObject/LicensePlate.php) |
| 4 | **Serviço** | Procedimento técnico/mecânico (ex: troca de óleo, alinhamento) solicitado para inclusão na OS. | Contexto Administrativo (Catálogo) / Atendimento | `Oficina\Catalog\Domain\...` |
| 5 | **Peça / Insumo** | Material físico necessário para a execução da OS, que possui quantidade controlada para baixa no estoque. | Contexto de Estoque (*Support Domain*) | `Oficina\Inventory\Domain\...` |
| 6 | **Orçamento** | Cálculo de valores gerado automaticamente pelo sistema, somando os preços das peças e serviços atrelados à OS. | Contexto de Atendimento (*Core Domain*) | `Oficina\ServiceOrder\Domain\...` |
| 7 | **Status da OS** | Estado atual da Ordem de Serviço que dita em que fase o veículo está. Transita entre: *Recebida*, *Em diagnóstico*, *Aguardando aprovação*, *Em execução*, *Finalizada*, *Entregue* (e *Cancelada*). | Contexto de Atendimento (*Core Domain*) | [`ServiceOrderStatus`](../../src/ServiceOrder/Domain/ServiceOrderStatus.php) |
| 8 | **Mecânico** | Pessoa física ou jurídica que executará os serviços contratados pelo Cliente, realizando comandos operacionais no sistema. | Contexto de Atendimento (*Core Domain*) | Ator autenticado (`Oficina\Identity\...`) |

---

## 2. Subdomínios e Bounded Contexts

Conforme o Mapa de Contextos (Página 2 de [`diagramas.pdf`](diagramas.pdf)):

1. **Contexto de Atendimento (*Core Domain*)**:
   - Criação da Ordem de Serviço (OS), Orçamentação automática, Aprovação/Rejeição e Acompanhamento em tempo real da execução.
   - Namespace: `Oficina\ServiceOrder` (`src/ServiceOrder`).
2. **Contexto de Estoque (*Support Domain*)**:
   - Gestão de Peças e Insumos, controle de disponibilidade, reserva e baixa de estoque.
   - Namespace: `Oficina\Inventory` (`src/Inventory`).
3. **Contexto Administrativo (*Generic Domain*)**:
   - Gestão de Clientes (`CPF/CNPJ`), Veículos (`Placa`, marca, modelo, ano) e Catálogo de Serviços.
   - Namespaces: `Oficina\Customer` (`src/Customer`) e `Oficina\Catalog` (`src/Catalog`).
4. **Módulo de Segurança (*Security / Auth*)**:
   - Autenticação JWT para proteger as APIs administrativas.
   - Namespace: `Oficina\Identity` (`src/Identity`).
5. **Shared Kernel**:
   - Objetos de Valor e Exceções de Domínio compartilhadas entre contextos ([`Document`](../../src/Shared/Domain/ValueObject/Document.php), [`DocumentType`](../../src/Shared/Domain/ValueObject/DocumentType.php), [`LicensePlate`](../../src/Shared/Domain/ValueObject/LicensePlate.php), [`DomainException`](../../src/Shared/Domain/Exception/DomainException.php), [`InvalidValueException`](../../src/Shared/Domain/Exception/InvalidValueException.php)).
   - Namespace: `Oficina\Shared` (`src/Shared`).

---

## 3. Agregados, Entidades e Objetos de Valor

Conforme o Diagrama Tático de Agregados (Página 3 de [`diagramas.pdf`](diagramas.pdf)):

| Elemento | Classificação DDD | Contexto | Objetos de Valor / Entidades Internas | Identificador de Referência Externa |
|---|---|---|---|---|
| **Ordem de Serviço** | Raiz de Agregado (*Aggregate Root*) | Atendimento | Entidades internas: `Item Serviço`, `Item Insumo/Peça`<br>Objetos de Valor: `StatusOS` ([`ServiceOrderStatus`](../../src/ServiceOrder/Domain/ServiceOrderStatus.php)), `Valor Orçamento` | Referencia `cliente_id`, `veiculo_id` e `item_id` por ID |
| **Cliente** | Raiz de Agregado (*Aggregate Root*) | Administrativo (`Customer`) | Objeto de Valor: `CpfCnpj` ([`Document`](../../src/Shared/Domain/ValueObject/Document.php)) | `cliente_id` (UUID) |
| **Veículo** | Raiz de Agregado (*Aggregate Root*) | Administrativo (`Customer`) | Objeto de Valor: `Placa` ([`LicensePlate`](../../src/Shared/Domain/ValueObject/LicensePlate.php)) | `veiculo_id` (UUID) |
| **Item Insumo / Peça** | Raiz de Agregado (*Aggregate Root*) | Estoque (`Inventory`) | Quantidade em estoque, preço unitário | `item_id` (UUID) |
| **Serviço** | Raiz de Agregado (*Aggregate Root*) | Catálogo (`Catalog`) | Preço base, tempo estimado de execução | `servico_id` (UUID) |
