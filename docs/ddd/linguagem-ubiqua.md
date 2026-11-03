# Linguagem Ubíqua e Glossário do Domínio

Este documento consolida a **Linguagem Ubíqua** utilizada pela equipe e pelos especialistas de domínio no **Sistema Integrado de Atendimento e Execução de Serviços**, bem como a relação direta entre os termos do negócio e sua implementação no código-fonte.

---

## 1. Glossário de Termos do Domínio

Conforme mapeado na documentação DDD do projeto (Página 8 de [`event-storming.pdf`](event-storming.pdf)):

| # | Termo | Definição do Negócio (Glossário) | Contexto Delimitado (Bounded Context) | Representação no Código (`Oficina\`) |
|---|---|---|---|---|
| 1 | **Ordem de Serviço (OS)** | Documento central que acompanha todo o ciclo de vida do atendimento, registrando o veículo, o cliente, os itens adicionados e o orçamento gerado. | Contexto de Atendimento (*Core Domain*) | [`ServiceOrder`](../../src/ServiceOrder/Domain/ServiceOrder.php) |
| 2 | **Cliente (`Customer`)** | Pessoa física ou jurídica que contrata os serviços, identificada unicamente e validada pelo seu CPF ou CNPJ. | Contexto Administrativo (*Generic Domain*) | [`Customer`](../../src/Customer/Domain/Customer.php) + VO [`Document`](../../src/Shared/Domain/ValueObject/Document.php) |
| 3 | **Veículo (`Vehicle`)** | O automóvel do cliente que passará pela manutenção, identificado unicamente por sua placa, marca, modelo e ano. | Contexto Administrativo (*Generic Domain*) | [`Vehicle`](../../src/Customer/Domain/Entity/Vehicle.php) + VO [`LicensePlate`](../../src/Shared/Domain/ValueObject/LicensePlate.php) |
| 4 | **Serviço** | Procedimento técnico/mecânico (ex: troca de óleo, alinhamento) solicitado para inclusão na OS. | Contexto Administrativo (Catálogo) / Atendimento | [`Service`](../../src/Catalog/Domain/Service.php) |
| 5 | **Peça / Insumo** | Material físico necessário para a execução da OS, que possui quantidade controlada para baixa no estoque. | Contexto de Estoque (*Support Domain*) | [`InventoryItem`](../../src/Inventory/Domain/InventoryItem.php) |
| 6 | **Orçamento** | Cálculo de valores gerado automaticamente pelo sistema, somando os preços das peças e serviços atrelados à OS. | Contexto de Atendimento (*Core Domain*) | [`Budget`](../../src/ServiceOrder/Domain/Budget.php) |
| 7 | **Status da OS** | Estado atual da Ordem de Serviço que dita em que fase o veículo está. Transita entre: *Recebida*, *Em diagnóstico*, *Aguardando aprovação*, *Em execução*, *Finalizada*, *Entregue* (e *Cancelada*). | Contexto de Atendimento (*Core Domain*) | [`ServiceOrderStatus`](../../src/ServiceOrder/Domain/ServiceOrderStatus.php) |
| 8 | **Mecânico** | Pessoa física ou jurídica que executará os serviços contratados pelo Cliente, realizando comandos operacionais no sistema. | Contexto de Atendimento (*Core Domain*) | Ator autenticado (`Oficina\Identity\...`) |
| 9 | **Administrador** | Usuário interno da oficina com acesso total às APIs administrativas. | Identidade | [`Role::ADMINISTRATOR`](../../src/Identity/Domain/Role.php) |
| 10 | **Usuário do Sistema (`SystemUser`)** | Conta de acesso que se autentica por credenciais na API. | Identidade | [`SystemUser`](../../src/Identity/Domain/SystemUser.php) |
| 11 | **Perfil de Acesso (`Role`)** | Nível de permissão do usuário: `Administrador`, `Mecânico` ou `Cliente`. | Identidade | [`Role`](../../src/Identity/Domain/Role.php) |
| 12 | **Vínculo Usuário-Cliente** | Relação de um para um (1:1) que associa uma conta de usuário com perfil `Cliente` ao seu cadastro de `Cliente`. | Identidade / Clientes | `SystemUser::$customerId` |
| 13 | **Token de Acesso (JWT)** | Credencial temporária (TTL de 60 min), enviada como `Authorization: Bearer`. | Identidade | `JwtTokenIssuer` |
| 14 | **Documento (`Document`)** | CPF (pessoa física) ou CNPJ (pessoa jurídica, inclusive alfanumérico). É obrigatório e válido, e identifica o cliente. | Shared Kernel | [`Document`](../../src/Shared/Domain/ValueObject/Document.php) |
| 15 | **E-mail (`Email`)** | Endereço de contato normalizado em minúsculas. | Shared Kernel | [`Email`](../../src/Shared/Domain/ValueObject/Email.php) |
| 16 | **Telefone (`Phone`)** | Telefone de contato com DDD válido (10 ou 11 dígitos). É opcional. | Clientes | [`Phone`](../../src/Customer/Domain/Phone.php) |
| 17 | **Cancelada** | Status final da OS encerrada sem execução: orçamento rejeitado, 48 h sem resposta ou desistência antes da finalização. | Contexto de Atendimento (*Core Domain*) | [`ServiceOrderStatus::CANCELED`](../../src/ServiceOrder/Domain/ServiceOrderStatus.php) + [`CancellationReason`](../../src/ServiceOrder/Domain/CancellationReason.php) |
| 18 | **Prazo de Aprovação** | Janela de 48 h, aberta no envio do orçamento, para o cliente aprovar ou rejeitar. | Contexto de Atendimento (*Core Domain*) | `ServiceOrder::APPROVAL_WINDOW_HOURS` |
| 19 | **Reparo Adicional** | Serviço ou peça encontrado durante a execução, que precisa de nova aprovação do cliente antes de ser feito. | Contexto de Atendimento (*Core Domain*) | `ServiceOrder::requestAdditionalRepair()` |
| 20 | **Preço Congelado** | Cópia do preço do catálogo/estoque feita quando o item entra no orçamento. Mudanças futuras de preço não alteram a OS. | Atendimento | [`ServiceLine`](../../src/ServiceOrder/Domain/ServiceLine.php) / [`PartLine`](../../src/ServiceOrder/Domain/PartLine.php) |
| 21 | **Peça Padrão** | Peça/insumo normalmente usada num serviço, sugerida quando o serviço entra na OS. | Catálogo | [`StandardPart`](../../src/Catalog/Domain/StandardPart.php) |
| 22 | **Reserva de Estoque** | Separação das peças de uma OS na aprovação do orçamento (tudo ou nada); vira baixa nos Serviços Finalizados ou é liberada se a OS for cancelada. | Estoque | [`StockReservation`](../../src/Inventory/Domain/StockReservation.php) |
| 23 | **Tempo de Execução** | Tempo entre a aprovação do orçamento e os Serviços Finalizados. A média por serviço é comparada ao tempo estimado do catálogo. | Atendimento | [`GetExecutionTimeReport`](../../src/ServiceOrder/Application/GetExecutionTimeReport.php) |

### Por que o status *Cancelada* (extensão aos 6 status do enunciado)

O enunciado lista *Recebida, Em diagnóstico, Aguardando aprovação, Em execução, Finalizada* e *Entregue*. No Event Storming o grupo acrescentou **Cancelada** como status final porque, sem ele, um orçamento rejeitado (ou sem resposta em 48 h) teria de passar por *Finalizada → Entregue*. Isso diria que a OS foi executada quando não foi, e entraria no cálculo do tempo médio de execução. Com *Cancelada*, o histórico fica fiel, o veículo vai para a fila de retirada e o motivo fica registrado (*Orçamento rejeitado pelo cliente*, *Orçamento sem resposta no prazo de aprovação* ou *Cancelada pela oficina*).

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
| **Ordem de Serviço** | Raiz de Agregado (*Aggregate Root*) | Atendimento | Entidades internas: `Item Serviço` ([`ServiceLine`](../../src/ServiceOrder/Domain/ServiceLine.php)), `Item Insumo/Peça` ([`PartLine`](../../src/ServiceOrder/Domain/PartLine.php))<br>Objetos de Valor: `StatusOS` ([`ServiceOrderStatus`](../../src/ServiceOrder/Domain/ServiceOrderStatus.php)), `Valor Orçamento` ([`Budget`](../../src/ServiceOrder/Domain/Budget.php)) | Referencia `cliente_id`, `veiculo_id` e `item_id` por ID |
| **Cliente** | Raiz de Agregado (*Aggregate Root*) | Administrativo (`Customer`) | Objetos de Valor: `CpfCnpj` ([`Document`](../../src/Shared/Domain/ValueObject/Document.php)), [`Email`](../../src/Shared/Domain/ValueObject/Email.php), [`Phone`](../../src/Customer/Domain/Phone.php) | `cliente_id` (UUID) |
| **Veículo** | Raiz de Agregado (*Aggregate Root*) | Administrativo (`Customer`) | Objeto de Valor: `Placa` ([`LicensePlate`](../../src/Shared/Domain/ValueObject/LicensePlate.php)) | `veiculo_id` (UUID) |
| **Item Insumo / Peça** | Raiz de Agregado (*Aggregate Root*) | Estoque (`Inventory`) | Quantidade em estoque, preço unitário | `item_id` (UUID) |
| **Serviço** | Raiz de Agregado (*Aggregate Root*) | Catálogo (`Catalog`) | Preço da mão de obra, tempo estimado, entidade interna `Peça Padrão` | `servico_id` (UUID) |
