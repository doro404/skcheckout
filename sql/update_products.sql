-- Adiciona coluna de tipo de entrega e dias de expiração na tabela products
ALTER TABLE products
ADD COLUMN delivery_type ENUM('download', 'email') NOT NULL DEFAULT 'download',
ADD COLUMN expiration_days INT NULL DEFAULT NULL;

-- Adiciona coluna de data de expiração na tabela transactions
ALTER TABLE transactions
ADD COLUMN expiration_date DATETIME NULL DEFAULT NULL; 