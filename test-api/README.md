# SCRIPT SQL para iniciar o Banco de Dados da API
-- Remove o banco se já existir
DROP DATABASE IF EXISTS `db_test_api`;

-- Cria o banco
CREATE DATABASE `db_test_api`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Remove o usuário (se já existir, remove também privilégios)
DROP USER IF EXISTS 'usuario_api'@'localhost';

-- Cria o usuário novamente (host = localhost)
CREATE USER 'usuario_api'@'localhost' IDENTIFIED BY '.!usuarioApi2025!.';

-- Concede privilégios completos no banco criado
GRANT ALL PRIVILEGES ON `db_test_api`.* TO 'usuario_api'@'localhost';

-- Aplica mudanças
FLUSH PRIVILEGES;

# Instruções especiais para copilação

# Bibliotecar de terceiros Utilizadas (Porque utilizou e como foram usadas)

# Link Para a documentaçãoda API ()