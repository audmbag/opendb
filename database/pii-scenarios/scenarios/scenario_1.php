<?php

declare(strict_types=1);

/**
 * Scénario 1 : schéma propre et bien typé, à grand volume. Sert de témoin : le clonage doit réussir.
 */
return [
    'title' => 'Témoin à grand volume (doit réussir)',
    'build' => static function (PiiScenarioBuilder $db): void {
        $db->run(<<<'SQL'
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                username VARCHAR(60) NOT NULL,
                password VARCHAR(255) NOT NULL,
                remember_token VARCHAR(100) NULL,
                phone VARCHAR(32) NULL,
                birth_date DATE NULL,
                gender VARCHAR(20) NULL,
                avatar VARCHAR(255) NULL,
                last_login_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY users_email_unique (email),
                UNIQUE KEY users_username_unique (username)
            ) COMMENT 'ATTENDU : succes. 100 000 lignes, email et username uniques.'
            SQL);

        $db->fill('users', 100000, [
            'id' => 'n',
            'first_name' => PiiSql::FIRST_NAME,
            'last_name' => PiiSql::LAST_NAME,
            'email' => PiiSql::EMAIL,
            'username' => PiiSql::USERNAME,
            'password' => PiiSql::PASSWORD_HASH,
            'remember_token' => 'IF(MOD(n, 3) = 0, NULL, '.PiiSql::SHA256.')',
            'phone' => 'IF(MOD(n, 5) = 0, NULL, '.PiiSql::PHONE.')',
            'birth_date' => 'IF(MOD(n, 7) = 0, NULL, '.PiiSql::BIRTH_DATE.')',
            'gender' => "ELT(1 + MOD(n, 4), 'female', 'male', 'other', NULL)",
            'avatar' => "IF(MOD(n, 2) = 0, NULL, CONCAT('avatars/', n, '.jpg'))",
            'last_login_ip' => PiiSql::IPV4,
            'created_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                street VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                postal_code VARCHAR(20) NOT NULL,
                country CHAR(2) NOT NULL,
                latitude DECIMAL(10, 7) NULL,
                longitude DECIMAL(10, 7) NULL,
                CONSTRAINT addresses_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
            ) COMMENT 'ATTENDU : succes. Colonnes de localisation aux types attendus.'
            SQL);

        $db->fill('addresses', 150000, [
            'user_id' => '1 + MOD(n * 7, '.$db->rows(100000).')',
            'street' => PiiSql::STREET,
            'city' => PiiSql::CITY,
            'postal_code' => PiiSql::POSTCODE,
            'country' => PiiSql::COUNTRY,
            'latitude' => PiiSql::LATITUDE,
            'longitude' => PiiSql::LONGITUDE,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                billing_address TEXT NOT NULL,
                shipping_address TEXT NULL,
                card_number VARCHAR(19) NOT NULL,
                card_holder VARCHAR(120) NOT NULL,
                exp_month TINYINT UNSIGNED NOT NULL,
                exp_year SMALLINT UNSIGNED NOT NULL,
                cvv VARCHAR(4) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY orders_status_index (status),
                CONSTRAINT orders_user_fk FOREIGN KEY (user_id) REFERENCES users (id)
            ) COMMENT 'ATTENDU : succes. Donnees de carte aux types attendus ; status ne doit pas etre modifie.'
            SQL);

        $db->fill('orders', 300000, [
            'user_id' => '1 + MOD(n * 11, '.$db->rows(100000).')',
            'billing_address' => 'CONCAT('.PiiSql::STREET.", '\\n', ".PiiSql::POSTCODE.", ' ', ".PiiSql::CITY.')',
            'shipping_address' => 'IF(MOD(n, 4) = 0, NULL, CONCAT('.PiiSql::STREET.", ', ', ".PiiSql::CITY.'))',
            'card_number' => PiiSql::CARD_NUMBER,
            'card_holder' => PiiSql::FULL_NAME,
            'exp_month' => '1 + MOD(n, 12)',
            'exp_year' => '2026 + MOD(n, 6)',
            'cvv' => "LPAD(MOD(n * 7, 1000), 3, '0')",
            'amount' => 'ROUND(5 + MOD(n * 31, 99000) / 100, 2)',
            'status' => "ELT(1 + MOD(n, 4), 'pending', 'paid', 'shipped', 'cancelled')",
            'created_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE page_views (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id CHAR(40) NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT NULL,
                url VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY page_views_session_index (session_id)
            ) COMMENT 'ATTENDU : succes. session_id est nullable, la strategie null est donc valide.'
            SQL);

        $db->fill('page_views', 500000, [
            'session_id' => 'IF(MOD(n, 6) = 0, NULL, SHA1(FLOOR(n / 5)))',
            'ip_address' => PiiSql::IPV4,
            'user_agent' => PiiSql::USER_AGENT,
            'url' => "CONCAT('/articles/', MOD(n, 5000))",
            'created_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE employees (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                manager_id BIGINT UNSIGNED NULL,
                full_name VARCHAR(200) NOT NULL,
                job_title VARCHAR(150) NOT NULL,
                company VARCHAR(150) NOT NULL,
                salary DECIMAL(12, 2) NOT NULL,
                iban VARCHAR(34) NOT NULL,
                bic VARCHAR(11) NOT NULL,
                national_id VARCHAR(20) NOT NULL,
                passport_number VARCHAR(20) NULL,
                emergency_contact_name VARCHAR(200) NULL,
                emergency_contact_phone VARCHAR(32) NULL,
                CONSTRAINT employees_manager_fk FOREIGN KEY (manager_id) REFERENCES employees (id)
            ) COMMENT 'ATTENDU : succes. Cle etrangere sur la meme table, donnees RH aux types attendus.'
            SQL);

        $db->fill('employees', 20000, [
            'id' => 'n',
            'manager_id' => 'IF(n <= 20, NULL, 1 + MOD(n, 20))',
            'full_name' => PiiSql::FULL_NAME,
            'job_title' => PiiSql::JOB_TITLE,
            'company' => PiiSql::COMPANY,
            'salary' => 'ROUND(22000 + MOD(n * 97, 90000), 2)',
            'iban' => PiiSql::IBAN,
            'bic' => "CONCAT('BNPAFRPP', LPAD(MOD(n, 1000), 3, '0'))",
            'national_id' => "CONCAT(1 + MOD(n, 2), LPAD(n, 12, '0'))",
            'passport_number' => "IF(MOD(n, 3) = 0, NULL, CONCAT('P', LPAD(n, 8, '0')))",
            'emergency_contact_name' => 'IF(MOD(n, 4) = 0, NULL, '.PiiSql::FULL_NAME.')',
            'emergency_contact_phone' => 'IF(MOD(n, 4) = 0, NULL, '.PiiSql::PHONE.')',
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE api_clients (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_name VARCHAR(150) NOT NULL,
                api_key VARCHAR(64) NOT NULL,
                client_secret VARCHAR(100) NOT NULL,
                website VARCHAR(255) NULL,
                UNIQUE KEY api_clients_api_key_unique (api_key)
            ) COMMENT 'ATTENDU : succes. Jetons uniques dans des colonnes assez larges.'
            SQL);

        $db->fill('api_clients', 5000, [
            'company_name' => PiiSql::COMPANY,
            'api_key' => PiiSql::SHA256,
            'client_secret' => PiiSql::SHA1,
            'website' => "CONCAT('https://client-', n, '.example')",
        ]);
    },
];
