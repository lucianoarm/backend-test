# API de Investimentos

API REST em **Symfony 7** para gerenciamento de investidores, investimentos e resgates.  
Utiliza **Doctrine ORM** para persistência de dados e **NelmioApiDocBundle** para documentação interativa no formato OpenAPI/Swagger.

## Tecnologias principais

- **PHP 8.3+**
- **Symfony 7**
- **Doctrine ORM**
- **MySQL**
- **Swagger/OpenAPI**

---

## Bibliotecas de terceiros adicionadas

| Biblioteca                              | Versão | Descrição no Projeto                                                                             |
|-----------------------------------------|--------|--------------------------------------------------------------------------------------------------|
| **doctrine/dbal**                       | 3.10.1 | Camada de abstração de banco usada pelo Doctrine ORM para consultas SQL e manipulação do schema. |
| **doctrine/doctrine-bundle**            | 2.15.1 | Integra o Doctrine ORM ao Symfony, lendo configs do `doctrine.yaml`.                             |
| **doctrine/doctrine-migrations-bundle** | 3.4.2  | Permite criar e rodar migrations para atualizar o schema do banco.                               |
| **doctrine/orm**                        | 3.5.2  | Mapeia entidades PHP para tabelas no banco de dados.                                             |
| **nelmio/api-doc-bundle**               | 5.5.0  | Gera documentação interativa dos endpoints usando Swagger UI.                                    |
| **symfony/mailer**                      | 7.3.x  | Envio de emails a partir da aplicação, usado no serviço de notificação.                          |
---

## Configuração e Compilação

**Pré-requisitos :**
- PHP 8.3+
- Composer 2.x
- MySQL ou outro banco suportado
- Extensões PHP: `pdo_mysql`, `mbstring`, `xml`, `intl`, `ctype`, `tokenizer`

### Clonar o projeto

> git clone https://github.com/lucianoarm/backend-test.git
> cd backend-test/test-api

### Instalar dependências

> composer install

### Configurar variáveis de ambiente

> **Copie o arquivo .env para .env.local e ajuste :**
> DATABASE_URL="mysql://usuario:senha@127.0.0.1:3306/investimentos"
> APP_ENV=dev
> APP_SECRET=algumasecret

> **symfony/mailer**
> MAILER_DSN=smtp://seu_usuario:sua_senha@smtp.gmail.com:587
> EMAIL_TO=email_destino_aviso@gmail.com

### Criar banco de dados e aplicar migrations

> php bin/console doctrine:database:create
> php bin/console doctrine:migrations:migrate

### Rodar servidor local

> symfony server:start

API em: http://127.0.0.1:8000 <- esta url será usada para acessar a documnetação interativa 
---

## Documentação da API

> **A documentação interativa gerada pelo Swagger está disponível em :**

> - http://127.0.0.1:8000/api/doc 

> A url e porta neste exemplo é a mesma retornada no passo anterior. 
> Neste documentação será possível testar os endpoints diretamente pelo navegador.

## Estrutura do Projeto

src/
 ├── Controller/
 |    └── Api/        → Endpoints da API
 ├── Entity/          → Entidades Doctrine
 ├── Repository/      → Repositórios de dados
 ├── Service/         → Calculo/Lógica de negócio
config/
 ├── packages/        → Configuração de bundles
 ├── routes/          → Arquivos de rotas
public/
 └── index.php        → Ponto de entrada da aplicação
