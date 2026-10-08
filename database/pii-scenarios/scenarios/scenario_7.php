<?php

declare(strict_types=1);

/**
 * Scénario 7 : volume. Tables de plusieurs millions de lignes, chaînes de clés étrangères et grand nombre de tables.
 */
return [
    'title' => 'Très gros volumes et graphe de clés étrangères',
    'build' => static function (PiiScenarioBuilder $db): void {
        $db->run(<<<'SQL'
            CREATE TABLE big_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                username VARCHAR(60) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                phone VARCHAR(32) NULL,
                password VARCHAR(255) NOT NULL,
                api_token VARCHAR(80) NULL,
                birth_date DATE NULL,
                last_login_ip VARCHAR(45) NULL,
                street VARCHAR(255) NULL,
                city VARCHAR(100) NULL,
                postal_code VARCHAR(20) NULL,
                country CHAR(2) NULL,
                created_at DATETIME NOT NULL
            ) COMMENT 'ATTENDU : succes si les generateurs restent uniques. Un million de lignes, trois index uniques sur des colonnes anonymisees.'
            SQL);

        $db->fill('big_users', 1000000, [
            'id' => 'n',
            'email' => PiiSql::EMAIL,
            'username' => PiiSql::USERNAME,
            'first_name' => PiiSql::FIRST_NAME,
            'last_name' => PiiSql::LAST_NAME,
            'phone' => 'IF(MOD(n, 4) = 0, NULL, '.PiiSql::PHONE.')',
            'password' => PiiSql::PASSWORD_HASH,
            'api_token' => 'IF(MOD(n, 3) = 0, NULL, '.PiiSql::SHA256.')',
            'birth_date' => PiiSql::BIRTH_DATE,
            'last_login_ip' => PiiSql::IPV4,
            'street' => PiiSql::STREET,
            'city' => PiiSql::CITY,
            'postal_code' => PiiSql::POSTCODE,
            'country' => PiiSql::COUNTRY,
            'created_at' => PiiSql::CREATED_AT,
        ]);

        // Index créés après le chargement : les construire triés est bien plus rapide que de les alimenter ligne à ligne.
        $db->run(<<<'SQL'
            ALTER TABLE big_users
                ADD UNIQUE KEY big_users_email_unique (email),
                ADD UNIQUE KEY big_users_username_unique (username),
                ADD UNIQUE KEY big_users_api_token_unique (api_token),
                ADD KEY big_users_created_index (created_at)
            SQL);

        $users = $db->rows(1000000);

        $db->run(<<<'SQL'
            CREATE TABLE big_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                session_id CHAR(40) NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NULL,
                event VARCHAR(30) NOT NULL,
                created_at DATETIME NOT NULL
            ) COMMENT 'ATTENDU : succes, tres long. Deux millions de lignes dont la cle etrangere doit suivre le remappage de big_users.id.'
            SQL);

        $db->fill('big_events', 2000000, [
            'user_id' => "1 + MOD(n * 7, {$users})",
            'session_id' => 'IF(MOD(n, 5) = 0, NULL, SHA1(FLOOR(n / 8)))',
            'ip_address' => PiiSql::IPV4,
            'user_agent' => PiiSql::USER_AGENT,
            'event' => "ELT(1 + MOD(n, 4), 'view', 'click', 'login', 'purchase')",
            'created_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            ALTER TABLE big_events
                ADD KEY big_events_session_index (session_id),
                ADD KEY big_events_created_index (created_at),
                ADD CONSTRAINT big_events_user_fk FOREIGN KEY (user_id) REFERENCES big_users (id)
            SQL);

        $db->run(<<<'SQL'
            CREATE TABLE uuid_accounts (
                id CHAR(36) NOT NULL PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                UNIQUE KEY uuid_accounts_email_unique (email)
            ) COMMENT 'ATTENDU : cle primaire UUID (non numerique), referencee par uuid_documents.'
            SQL);

        $db->fill('uuid_accounts', 200000, [
            'id' => PiiSql::UUID,
            'email' => PiiSql::EMAIL,
            'full_name' => PiiSql::FULL_NAME,
        ]);

        $accounts = $db->rows(200000);
        $accountUuid = str_replace('n)', "1 + MOD(n * 3, {$accounts}))", PiiSql::UUID);

        $db->run(<<<'SQL'
            CREATE TABLE uuid_documents (
                id CHAR(36) NOT NULL PRIMARY KEY,
                account_id CHAR(36) NOT NULL,
                iban VARCHAR(34) NULL,
                national_id VARCHAR(20) NULL,
                CONSTRAINT uuid_documents_account_fk FOREIGN KEY (account_id) REFERENCES uuid_accounts (id)
            ) COMMENT 'ATTENDU : succes. 400 000 lignes avec cle etrangere UUID.'
            SQL);

        $db->fill('uuid_documents', 400000, [
            'id' => 'UUID()',
            'account_id' => $accountUuid,
            'iban' => PiiSql::IBAN,
            'national_id' => "CONCAT('ID', LPAD(n, 12, '0'))",
        ]);

        $previous = null;
        foreach (range(1, 8) as $level) {
            $table = "chain_level_{$level}";
            $foreign = $previous === null ? '' : ",\n    parent_id BIGINT UNSIGNED NOT NULL,\n    CONSTRAINT {$table}_parent_fk FOREIGN KEY (parent_id) REFERENCES {$previous} (id)";

            $db->run("CREATE TABLE {$table} (\n    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    email VARCHAR(191) NOT NULL,\n    phone VARCHAR(32) NULL{$foreign}\n) COMMENT 'ATTENDU : succes. Maillon {$level} sur 8 d une chaine de cles etrangeres.'");

            $values = ['id' => 'n', 'email' => "CONCAT('l{$level}-', n, '@chain.example')", 'phone' => PiiSql::PHONE];
            if ($previous !== null) {
                $values['parent_id'] = '1 + MOD(n * 3, '.$db->rows(50000).')';
            }

            $db->fill($table, 50000, $values);
            $previous = $table;
        }

        foreach (range(1, 30) as $index) {
            $table = sprintf('satellite_%02d', $index);

            $db->run("CREATE TABLE {$table} (\n    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    user_id BIGINT UNSIGNED NOT NULL,\n    email VARCHAR(191) NULL,\n    ip_address VARCHAR(45) NULL,\n    note VARCHAR(100) NULL,\n    CONSTRAINT {$table}_user_fk FOREIGN KEY (user_id) REFERENCES big_users (id)\n) COMMENT 'ATTENDU : succes. Une des 30 tables qui referencent toutes big_users.id.'");

            $db->fill($table, 20000, [
                'user_id' => "1 + MOD(n * {$index}, {$users})",
                'email' => "CONCAT('sat{$index}-', n, '@corp.example')",
                'ip_address' => PiiSql::IPV4,
                'note' => "CONCAT('note ', n)",
            ]);
        }

        foreach (range(1, 120) as $index) {
            $table = sprintf('misc_%03d', $index);

            $db->run("CREATE TABLE {$table} (\n    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    name VARCHAR(150) NOT NULL,\n    email VARCHAR(191) NULL,\n    phone VARCHAR(32) NULL,\n    city VARCHAR(100) NULL\n) COMMENT 'ATTENDU : succes. Une des 120 petites tables, pour mesurer le cout par table.'");

            $db->fill($table, 500, [
                'name' => PiiSql::FULL_NAME,
                'email' => "CONCAT('misc{$index}-', n, '@corp.example')",
                'phone' => PiiSql::PHONE,
                'city' => PiiSql::CITY,
            ]);
        }
    },
];
