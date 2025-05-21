-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 21/05/2025 às 04:08
-- Versão do servidor: 10.11.10-MariaDB-cll-lve
-- Versão do PHP: 8.3.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `wuyhiqve_checkout`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `merchants`
--

CREATE TABLE `merchants` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `default_callback_url` varchar(255) DEFAULT NULL,
  `use_dynamic_callback` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `merchants`
--

INSERT INTO `merchants` (`id`, `name`, `email`, `password`, `api_key`, `webhook_url`, `created_at`, `updated_at`, `is_admin`, `default_callback_url`, `use_dynamic_callback`) VALUES
(3, 'LOJA ONLINE', 'admin@gmail.com', '$2y$10$DqNpzj5i9QTwVw5qPvYtN.tzVs1Q0ja3Dsgnx6CKJgW/mNj5WcVA.', 'afd1689e12c09a6cd6bd261367bd66b3e3fd69bce4c7dd137985e7c53350b897', 'https://webhook.site/9fa7e0c1-a935-4e36-895f-d581c0d2e020', '2025-05-18 04:35:30', '2025-05-21 04:00:11', 1, 'https://sitederetorno/', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive','cancelled') DEFAULT NULL,
  `type` enum('ebook','curso','software','assinatura','outro') NOT NULL,
  `delivery_type` enum('download','email','acesso_online') NOT NULL,
  `delivery_info` text DEFAULT NULL,
  `access_duration` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `product_callback_url` varchar(255) DEFAULT NULL,
  `use_custom_callback` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'environment', 'production', 'Ambiente do sistema (development/production)', 1, '2025-05-18 05:46:41', '2025-05-18 06:21:51'),
(2, 'debug', 'true', 'Modo de debug ativado', 0, '2025-05-18 05:46:41', '2025-05-18 05:46:41'),
(3, 'mp_access_token', 'APP_USR-1185015250939011-111419-fe2d5f0429513e9117db6600a83dee8f-1167329520', 'Token de acesso do Mercado Pago', 0, '2025-05-18 05:46:41', '2025-05-21 04:02:45'),
(4, 'payment_expiration_minutes', '30', 'Tempo de expiração do PIX em minutos', 1, '2025-05-18 05:46:41', '2025-05-20 02:58:39'),
(5, 'min_payment_amount', '1.00', 'Valor mínimo de pagamento', 1, '2025-05-18 05:46:41', '2025-05-18 05:46:41'),
(6, 'max_payment_amount', '10000.00', 'Valor máximo de pagamento', 1, '2025-05-18 05:46:41', '2025-05-18 05:46:41'),
(7, 'base_url', 'https://dominio/pix-payments', 'URL base do sistema', 1, '2025-05-18 05:46:41', '2025-05-21 03:18:00'),
(8, 'webhook_url', 'https://dominio/webhook.php', 'URL para webhooks | ALTERE A PARTE DO DOMINIO SEM CONFIGURAR ISSO NADA FUNCIONA', 1, '2025-05-18 05:46:41', '2025-05-21 03:17:21'),
(9, 'smtp_host', '', 'Host do servidor SMTP', 0, '2025-05-18 05:46:41', '2025-05-21 03:25:34'),
(10, 'smtp_user', '', 'Usuário SMTP', 0, '2025-05-18 05:46:41', '2025-05-21 03:17:08'),
(11, 'smtp_pass', '', 'Senha SMTP', 0, '2025-05-18 05:46:41', '2025-05-21 03:17:05'),
(12, 'smtp_port', '', 'Porta SMTP', 0, '2025-05-18 05:46:41', '2025-05-21 03:17:02'),
(13, 'smtp_from', '', 'E-mail de envio', 0, '2025-05-18 05:46:41', '2025-05-21 03:16:59'),
(14, 'smtp_from_name', 'Sistema de Pagamento PIX', 'Nome de exibição do e-mail', 0, '2025-05-18 05:46:41', '2025-05-18 05:46:41'),
(15, 'webhook_secret', 'seu_webhook_secret_aqui', 'Chave secreta para webhooks (opcional)', 0, '2025-05-18 05:46:41', '2025-05-20 02:44:09'),
(16, 'security_salt', 'sua_chave_secreta_aqui', 'Salt para funções de segurança (opcional)', 0, '2025-05-18 05:46:41', '2025-05-20 02:44:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `payment_id` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL,
  `payer_name` varchar(100) DEFAULT NULL,
  `payer_email` varchar(100) DEFAULT NULL,
  `payer_document` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `callback_url` varchar(255) DEFAULT NULL COMMENT 'URL de retorno após o pagamento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `merchants`
--
ALTER TABLE `merchants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `api_key` (`api_key`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_id` (`merchant_id`);

--
-- Índices de tabela `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`);

--
-- Índices de tabela `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_id` (`merchant_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `merchants`
--
ALTER TABLE `merchants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`);

--
-- Restrições para tabelas `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
