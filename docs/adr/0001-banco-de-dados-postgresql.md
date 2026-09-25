# ADR 0001 — PostgreSQL como banco de dados

**Status:** Proposto (validar com o grupo)

## Contexto
O domínio da oficina é fortemente transacional: criar uma OS envolve cliente, veículo,
serviços, peças e **baixa/reserva de estoque** na mesma operação. O desafio exige
"tratamento adequado das transações envolvidas nos principais fluxos".

## Decisão
PostgreSQL 17.

## Justificativa
- **ACID e consistência forte**: aprovação de orçamento + reserva de estoque precisam ser atômicas.
- **Controle de concorrência**: `SELECT ... FOR UPDATE` e isolamento configurável evitam venda
  de peça sem saldo quando dois mecânicos usam a mesma peça simultaneamente.
- **Integridade no banco**: `CHECK (quantidade >= 0)`, FKs e constraints de unicidade (placa, documento)
  como segunda linha de defesa além do domínio.
- **Relacional por natureza**: OS ↔ itens ↔ peças ↔ cliente ↔ veículo é um grafo relacional clássico.
- **JSONB** disponível para histórico/auditoria de transições de status sem nova infraestrutura.
- **Custo**: open-source, suportado por todos os clouds gerenciados (RDS, Cloud SQL, Neon) — caminho
  natural para as próximas fases do curso.

## Alternativas descartadas
- **MySQL**: viável, mas CHECK constraints e tipos avançados são historicamente mais limitados.
- **MongoDB**: transações multi-documento existem, mas o modelo é relacional e a consistência
  de estoque ficaria mais custosa de garantir.
