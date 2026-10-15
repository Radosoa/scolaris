-- Base dédiée aux tests automatisés
CREATE DATABASE IF NOT EXISTS scolaris_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON scolaris_test.* TO 'scolaris'@'%';
