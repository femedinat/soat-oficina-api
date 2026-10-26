# Linguagem Ubíqua e Glossário

Este documento estabelece o vocabulário padronizado e os termos de negócio utilizados entre especialistas de domínio e equipe técnica para a **Oficina API**.

| Termo | Contexto | Definição |
|---|---|---|
| **Administrador** | Identidade | Agregado raiz. Usuário interno da oficina com acesso às APIs administrativas. |
| **Usuário do Sistema (`SystemUser`)** | Identidade | Conta de acesso que se autentica por credenciais na API. |
| **Perfil de Acesso (`Role`)** | Identidade | Nível de permissão do usuário: `Administrador`, `Mecânico` ou `Cliente`. |
| **Credenciais** | Identidade | E-mail e senha usados para autenticar o usuário. |
| **Autenticação** | Identidade | Processo de comprovar a identidade e receber um token. |
| **Token de Acesso (JWT)** | Identidade | Credencial temporária (TTL de 60 min), enviada como `Authorization: Bearer`. |
| **Encerramento de Sessão (Logout)** | Identidade | Invalidação do token atual (blacklist). |
| **Credenciais inválidas** | Identidade | Falha de login. A mensagem é genérica para não revelar se o e-mail existe. |
| **Cliente (`Customer`)** | Clientes | Agregado raiz. Pessoa física ou jurídica atendida pela oficina. |
| **Identificador do Cliente** | Clientes | UUID que identifica o cliente. |
| **Vínculo Usuário-Cliente** | Identidade / Clientes | Relação de um para um (1:1) que associa uma conta de usuário com perfil `Cliente` ao seu cadastro de `Cliente`. |
| **Documento (`Document`)** | Shared Kernel | CPF (pessoa física) ou CNPJ (pessoa jurídica, inclusive alfanumérico). É obrigatório e válido, e identifica o cliente. |
| **Tipo de Documento** | Shared Kernel | CPF ou CNPJ. |
| **E-mail** | Shared Kernel | Endereço de contato, normalizado em minúsculas. |
| **Telefone (`Phone`)** | Clientes | Telefone de contato com DDD. É opcional. |
| **Cadastrar Cliente** | Clientes | Criar o cliente, com validação e unicidade de documento. |
| **Revisar Cliente** | Clientes | Atualizar os dados do cliente, mantendo a identidade. |
| **Consultar / Listar / Excluir Cliente** | Clientes | Operações do ciclo de vida e CRUD do cliente. |
| **Documento já cadastrado** | Clientes | Violação da regra de documento único (HTTP 409). |
| **Cliente não encontrado** | Clientes | Cliente inexistente (HTTP 404). |
