-- Base dédiée aux tests automatisés
CREATE DATABASE IF NOT EXISTS scolaris_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON scolaris_test.* TO 'scolaris'@'%';

-- Multi-établissement : base centrale (liste des écoles) et une base numérotée par école
-- (scolaris500001, scolaris500002…). L'application crée elle-même ces bases : droits sur « scolaris* ».
CREATE DATABASE IF NOT EXISTS scolaris_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON `scolaris%`.* TO 'scolaris'@'%';
