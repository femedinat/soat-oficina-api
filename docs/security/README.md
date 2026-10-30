# Análise de vulnerabilidades

Evidência da versão entregue. A análise roda automaticamente em **todo PR e push** pelo workflow [`ci.yml`](../../.github/workflows/ci.yml); um achado `CRITICAL` reprova o PR.

## Resultado da última execução

| Ferramenta | O que verifica | Resultado | Evidência |
|---|---|---|---|
| **Trivy** (`fs`, scanners `vuln,secret,misconfig`) | CVEs nas dependências do `composer.lock`, segredos versionados e má configuração (Dockerfile, compose) | 0 vulnerabilidades e 0 segredos (gate CRITICAL); misconfig no artefato | [`trivy-composer-audit-2026-10-29.txt`](trivy-composer-audit-2026-10-29.txt) e artefato `security-report` da [execução](https://github.com/femedinat/soat-oficina-api/actions/runs/40963293165) |
| **composer audit** | Advisories de segurança do ecossistema PHP (base do Packagist/FriendsOfPHP) | Nenhum advisory | Job *Lint & análise estática* da mesma execução |
| **Larastan (nível 6)** | Erros de tipo e uso inseguro de APIs detectáveis estaticamente | 0 erros | Mesmo job |
| **Dependabot** | Atualização semanal de dependências Composer, Docker e Actions | PRs automáticos (ex.: #10, #11, #14, #15) | [`dependabot.yml`](../../.github/dependabot.yml) |

> Para atualizar esta evidência na versão final: abra a execução do CI do commit entregue, baixe o artefato `security-report` (ou copie a tabela do passo *Exibir relatório no log*) e substitua o arquivo `.txt` acima.

## Controles de segurança no código (OWASP Top 10 2021)

| Risco OWASP | Controle aplicado | Onde |
|---|---|---|
| A01 Quebra de controle de acesso | JWT obrigatório nas APIs administrativas; perfis `administrator`, `mechanic`, `customer` por rota; cliente só acessa as próprias OS (outra OS responde 404, sem revelar existência) | [`routes/api.php`](../../routes/api.php), [`RequireRole`](../../src/Identity/Interfaces/Http/RequireRole.php), [`ServiceOrderCommand`](../../src/ServiceOrder/Application/ServiceOrderCommand.php) |
| A02 Falhas criptográficas | Senhas com bcrypt; JWT assinado com segredo do `.env` (fora do repositório); comparação de documento em tempo constante (`hash_equals`) no acompanhamento público | [`LaravelPasswordHasher`](../../src/Identity/Infrastructure/LaravelPasswordHasher.php), [`TrackServiceOrder`](../../src/ServiceOrder/Application/TrackServiceOrder.php) |
| A03 Injeção | Somente query builder/Eloquent com bindings; curingas de `LIKE` escapados; e-mails em texto puro | Repositórios `Eloquent*` |
| A04 Design inseguro | Regras no domínio (máquina de estados da OS, reserva tudo-ou-nada), transações com `SELECT ... FOR UPDATE`, `CHECK` constraints no PostgreSQL como última barreira | `src/*/Domain`, migrations |
| A05 Configuração incorreta | API sem sessão; respostas JSON sem stack trace; banco publicado só em `127.0.0.1`; imagem de produção não-root | `bootstrap/app.php`, `docker-compose.yml`, `docker/php/Dockerfile` |
| A06 Componentes vulneráveis | Trivy + `composer audit` no CI e Dependabot; Actions fixadas por SHA (supply chain) | `ci.yml`, `dependabot.yml` |
| A07 Falhas de autenticação | Login com *rate limit* (10/min) e mesma mensagem para usuário inexistente ou senha errada (sem enumeração); consulta pública limitada a 20/min por IP | `routes/api.php`, [`AuthenticateSystemUser`](../../src/Identity/Application/AuthenticateSystemUser.php) |
| A08 Integridade de software e dados | Histórico de status *append-only*; eventos de domínio publicados só após o commit | [`EloquentServiceOrderRepository`](../../src/ServiceOrder/Infrastructure/EloquentServiceOrderRepository.php), [`LaravelEventPublisher`](../../src/Shared/Infrastructure/LaravelEventPublisher.php) |
| A09 Falhas de log e monitoramento | Falhas de envio de e-mail registradas em log sem dados pessoais | `LaravelMail*Notifier` |
| A10 SSRF | A API não faz requisições a URLs informadas pelo usuário | — |

**Dados sensíveis (LGPD):** CPF/CNPJ e placa validados por Objetos de Valor ([`Document`](../../src/Shared/Domain/ValueObject/Document.php), [`LicensePlate`](../../src/Shared/Domain/ValueObject/LicensePlate.php)); o acompanhamento público não devolve nome, documento, e-mail nem itens da OS.
