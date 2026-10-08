<?php

declare(strict_types=1);

/**
 * Scénario 5 : données personnelles utilisées comme clés, et contraintes que la valeur générée peut violer.
 */
return [
    'title' => 'Clés, contraintes, colonnes générées, triggers et vues',
    'build' => static function (PiiScenarioBuilder $db): void {
        $db->run(<<<'SQL'
            CREATE TABLE accounts (
                email VARCHAR(191) NOT NULL PRIMARY KEY,
                full_name VARCHAR(200) NOT NULL,
                phone VARCHAR(32) NULL,
                created_at DATETIME NOT NULL
            ) COMMENT 'ATTENDU : email est la cle primaire naturelle, referencee par account_logins et invoices.'
            SQL);

        $db->fill('accounts', 30000, [
            'email' => PiiSql::EMAIL,
            'full_name' => PiiSql::FULL_NAME,
            'phone' => PiiSql::PHONE,
            'created_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE account_logins (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NULL,
                logged_at DATETIME NOT NULL,
                CONSTRAINT account_logins_email_fk FOREIGN KEY (email) REFERENCES accounts (email) ON UPDATE CASCADE ON DELETE CASCADE
            ) COMMENT 'ATTENDU : echec ou relation cassee. email est une cle etrangere vers accounts.email.'
            SQL);

        $db->fill('account_logins', 90000, [
            'email' => "CONCAT('user', 1 + MOD(n * 7, ".$db->rows(30000)."), '@corp.example')",
            'ip_address' => PiiSql::IPV4,
            'user_agent' => PiiSql::USER_AGENT,
            'logged_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE profiles (
                username VARCHAR(60) NOT NULL PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                website VARCHAR(255) NULL
            ) COMMENT 'ATTENDU : username est la cle primaire naturelle, referencee par posts.'
            SQL);

        $db->fill('profiles', 20000, [
            'username' => PiiSql::USERNAME,
            'first_name' => PiiSql::FIRST_NAME,
            'last_name' => PiiSql::LAST_NAME,
            'website' => "CONCAT('https://blog-', n, '.example')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE posts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(60) NOT NULL,
                title VARCHAR(200) NOT NULL,
                position INT NOT NULL,
                role VARCHAR(30) NOT NULL,
                state VARCHAR(20) NOT NULL,
                CONSTRAINT posts_username_fk FOREIGN KEY (username) REFERENCES profiles (username)
            ) COMMENT 'ATTENDU : username est une cle etrangere ; title, position, role et state sont des colonnes metier qui ne doivent pas etre modifiees.'
            SQL);

        $db->fill('posts', 80000, [
            'username' => "CONCAT('user_', 1 + MOD(n * 3, ".$db->rows(20000).'))',
            'title' => "CONCAT('Article numero ', n)",
            'position' => 'MOD(n, 50)',
            'role' => "ELT(1 + MOD(n, 3), 'author', 'editor', 'admin')",
            'state' => "ELT(1 + MOD(n, 3), 'draft', 'published', 'archived')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE patients (
                patient_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(200) NOT NULL,
                birth_date DATE NOT NULL,
                mrn VARCHAR(20) NOT NULL,
                policy_number VARCHAR(20) NOT NULL,
                UNIQUE KEY patients_mrn_unique (mrn),
                UNIQUE KEY patients_policy_unique (policy_number)
            ) COMMENT 'ATTENDU : patient_id est une cle primaire auto-incrementee visee par un matcher ; mrn et policy_number sont uniques.'
            SQL);

        $db->fill('patients', 20000, [
            'patient_id' => 'n',
            'full_name' => PiiSql::FULL_NAME,
            'birth_date' => PiiSql::BIRTH_DATE,
            'mrn' => "CONCAT('MRN-', LPAD(n, 6, '0'))",
            'policy_number' => "CONCAT('POL-', LPAD(n, 9, '0'))",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE appointments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                patient_id INT UNSIGNED NOT NULL,
                policy_number VARCHAR(20) NOT NULL,
                diagnosis VARCHAR(255) NULL,
                scheduled_at DATETIME NOT NULL,
                CONSTRAINT appointments_patient_fk FOREIGN KEY (patient_id) REFERENCES patients (patient_id),
                CONSTRAINT appointments_policy_fk FOREIGN KEY (policy_number) REFERENCES patients (policy_number)
            ) COMMENT 'ATTENDU : echec ou relation cassee. Deux cles etrangeres visees par des matchers, dont une vers une colonne unique non primaire.'
            SQL);

        $db->fill('appointments', 80000, [
            'patient_id' => '1 + MOD(n * 3, '.$db->rows(20000).')',
            'policy_number' => "CONCAT('POL-', LPAD(1 + MOD(n * 3, ".$db->rows(20000)."), 9, '0'))",
            'diagnosis' => "IF(MOD(n, 4) = 0, NULL, CONCAT('Code A', MOD(n, 99)))",
            'scheduled_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE user_devices (
                user_id BIGINT UNSIGNED NOT NULL,
                device_id CHAR(36) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                PRIMARY KEY (user_id, device_id)
            ) COMMENT 'ATTENDU : cle primaire composee dont un membre (device_id) est vise par un matcher.'
            SQL);

        $db->fill('user_devices', 60000, [
            'user_id' => '1 + FLOOR(n / 3)',
            'device_id' => PiiSql::UUID,
            'ip_address' => PiiSql::IPV4,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE teams (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                lead_person_id BIGINT UNSIGNED NULL,
                UNIQUE KEY teams_name_unique (name)
            ) COMMENT 'ATTENDU : name est unique et designe une equipe, pas une personne. Reference circulaire avec people.'
            SQL);

        $db->fill('teams', 500, [
            'id' => 'n',
            'name' => "CONCAT('Equipe ', n)",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE people (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                team_id BIGINT UNSIGNED NOT NULL,
                manager_id BIGINT UNSIGNED NULL,
                name VARCHAR(200) NOT NULL,
                email VARCHAR(191) NOT NULL,
                UNIQUE KEY people_email_unique (email),
                CONSTRAINT people_team_fk FOREIGN KEY (team_id) REFERENCES teams (id),
                CONSTRAINT people_manager_fk FOREIGN KEY (manager_id) REFERENCES people (id)
            ) COMMENT 'ATTENDU : succes si les cles sont gerees. Cle etrangere sur soi-meme et cycle teams <-> people.'
            SQL);

        $db->fill('people', 25000, [
            'id' => 'n',
            'team_id' => '1 + MOD(n, '.$db->rows(500).')',
            'manager_id' => 'IF(n <= 50, NULL, 1 + MOD(n, 50))',
            'name' => PiiSql::FULL_NAME,
            'email' => PiiSql::EMAIL,
        ]);

        $db->run('ALTER TABLE teams ADD CONSTRAINT teams_lead_fk FOREIGN KEY (lead_person_id) REFERENCES people (id)');
        $db->run('UPDATE teams SET lead_person_id = id');

        $checks = [
            'chk_minors' => ['age TINYINT UNSIGNED NOT NULL', 'age BETWEEN 0 AND 17', 'MOD(n, 18)', 'echec : numberBetween 18-90 viole la contrainte (table de mineurs)'],
            'chk_corporate_email' => ['email VARCHAR(191) NOT NULL', "email LIKE '%@corp.example'", PiiSql::EMAIL, 'echec : le domaine genere est example.org'],
            'chk_french_phone' => ['phone VARCHAR(20) NOT NULL', "phone REGEXP '^0[1-9][0-9]{8}$'", "CONCAT('06', LPAD(n, 8, '0'))", 'echec : le format E.164 commence par +'],
            'chk_country_list' => ['country CHAR(2) NOT NULL', "country IN ('FR', 'BE', 'CH')", "ELT(1 + MOD(n, 3), 'FR', 'BE', 'CH')", 'echec : countryCode tire dans tous les pays'],
            'chk_france_latitude' => ['latitude DECIMAL(9, 6) NOT NULL', 'latitude BETWEEN 41 AND 52', PiiSql::LATITUDE, 'echec : latitude tiree sur tout le globe'],
            'chk_french_iban' => ['iban VARCHAR(34) NOT NULL', "iban LIKE 'FR%'", PiiSql::IBAN, 'echec : iban tire dans tous les pays'],
            'chk_card_16_digits' => ['card_number VARCHAR(19) NOT NULL', 'CHAR_LENGTH(card_number) = 16', PiiSql::CARD_NUMBER, 'echec : creditCardNumber fait de 13 a 16 chiffres'],
            'chk_alpha_username' => ['username VARCHAR(30) NOT NULL', "username REGEXP '^[a-z_]+$'", "CONCAT('user_', CHAR(97 + MOD(n, 26) USING utf8mb4), CHAR(97 + MOD(FLOOR(n / 26), 26) USING utf8mb4), CHAR(97 + MOD(FLOOR(n / 676), 26) USING utf8mb4))", 'echec si la valeur contient des chiffres'],
            'chk_six_digit_pin' => ['pin CHAR(6) NOT NULL', "pin REGEXP '^[0-9]{6}$'", "LPAD(n, 6, '0')", 'echec : numerify #### ne produit que 4 chiffres'],
            'chk_recent_birth' => ['birth_date DATE NOT NULL', "birth_date >= '2010-01-01'", "DATE_ADD('2010-01-01', INTERVAL MOD(n, 5000) DAY)", 'echec : la date generee a toujours plus de 18 ans'],
            'chk_long_password' => ['password VARCHAR(255) NOT NULL', 'CHAR_LENGTH(password) >= 60', PiiSql::PASSWORD_HASH, 'succes : le hash bcrypt fait 60 caracteres'],
            'chk_future_expiry' => ['card_expiry DATE NOT NULL', "card_expiry >= '2026-01-01'", "DATE_ADD('2026-01-31', INTERVAL MOD(n, 60) MONTH)", 'echec : 92 % des dates generees sont dans le passe'],
        ];

        foreach ($checks as $table => [$definition, $check, $value, $expected]) {
            $column = strtok($definition, ' ');

            $db->run("CREATE TABLE {$table} (\n    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    {$definition},\n    CONSTRAINT {$table}_check CHECK ({$check})\n) COMMENT 'ATTENDU : ".str_replace("'", "''", $expected).".'");
            $db->fill($table, 5000, [$column => $value]);
        }

        $db->run(<<<'SQL'
            CREATE TABLE generated_people (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(191) NOT NULL,
                birth_date DATE NOT NULL,
                full_name VARCHAR(201) GENERATED ALWAYS AS (CONCAT(first_name, ' ', last_name)) STORED,
                username VARCHAR(191) GENERATED ALWAYS AS (LOWER(SUBSTRING_INDEX(email, '@', 1))) STORED,
                age SMALLINT GENERATED ALWAYS AS (TIMESTAMPDIFF(YEAR, birth_date, '2026-01-01')) VIRTUAL,
                UNIQUE KEY generated_people_username_unique (username),
                KEY generated_people_age_index (age)
            ) COMMENT 'ATTENDU : echec si le clonage ecrit dans full_name, username ou age : ce sont des colonnes generees, visees par des matchers.'
            SQL);

        $db->fill('generated_people', 20000, [
            'first_name' => PiiSql::FIRST_NAME,
            'last_name' => PiiSql::LAST_NAME,
            'email' => PiiSql::EMAIL,
            'birth_date' => PiiSql::BIRTH_DATE,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE functional_index_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) COLLATE utf8mb4_bin NOT NULL,
                phone VARCHAR(32) NOT NULL,
                UNIQUE KEY functional_index_users_lower_email ((LOWER(email))),
                UNIQUE KEY functional_index_users_digits_phone ((REPLACE(phone, ' ', '')))
            ) COMMENT 'ATTENDU : succes probable. Index uniques fonctionnels (expression) sur email et phone.'
            SQL);

        $db->fill('functional_index_users', 20000, [
            'email' => "CONCAT('User', n, '@Corp.example')",
            'phone' => "CONCAT('06 ', LPAD(n, 8, '0'))",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE members (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) NOT NULL,
                full_name VARCHAR(200) NOT NULL
            ) COMMENT 'ATTENDU : un trigger refuse tout email hors du domaine members.example et un autre journalise chaque insertion.'
            SQL);

        $db->run(<<<'SQL'
            CREATE TABLE member_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(191) NOT NULL,
                action VARCHAR(20) NOT NULL
            ) COMMENT 'ATTENDU : remplie par trigger. Si les triggers sont recopies et actifs pendant le clonage, les lignes sont inserees deux fois.'
            SQL);

        $db->attempt('trigger de validation sur members', <<<'SQL'
            CREATE TRIGGER members_validate_email BEFORE INSERT ON members FOR EACH ROW
            BEGIN
                IF NEW.email NOT LIKE '%@members.example' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'email must belong to members.example';
                END IF;
            END
            SQL);

        $db->attempt('trigger de journalisation sur members', <<<'SQL'
            CREATE TRIGGER members_audit_insert AFTER INSERT ON members FOR EACH ROW
                INSERT INTO member_audit (member_id, email, action) VALUES (NEW.id, NEW.email, 'insert')
            SQL);

        $db->fill('members', 20000, [
            'email' => "CONCAT('member', n, '@members.example')",
            'full_name' => PiiSql::FULL_NAME,
        ]);

        $db->run(<<<'SQL'
            CREATE VIEW contact_directory AS
                SELECT a.email, a.full_name, a.phone, COUNT(l.id) AS logins
                  FROM accounts a
                  LEFT JOIN account_logins l ON l.email = a.email
                 GROUP BY a.email, a.full_name, a.phone
            SQL);

        $db->run(<<<'SQL'
            CREATE TABLE partitioned_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at DATETIME NOT NULL,
                email VARCHAR(191) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                session_id CHAR(40) NULL,
                PRIMARY KEY (id, created_at),
                KEY partitioned_events_email_index (email)
            ) COMMENT 'ATTENDU : succes. Table partitionnee par annee.'
            PARTITION BY RANGE (YEAR(created_at)) (
                PARTITION p2020 VALUES LESS THAN (2021),
                PARTITION p2021 VALUES LESS THAN (2022),
                PARTITION p2022 VALUES LESS THAN (2023),
                PARTITION p2023 VALUES LESS THAN (2024),
                PARTITION p2024 VALUES LESS THAN (2025),
                PARTITION pmax VALUES LESS THAN MAXVALUE
            )
            SQL);

        $db->fill('partitioned_events', 200000, [
            'created_at' => PiiSql::CREATED_AT,
            'email' => PiiSql::EMAIL,
            'ip_address' => PiiSql::IPV4,
            'session_id' => 'IF(MOD(n, 3) = 0, NULL, '.PiiSql::SHA1.')',
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE contacts_without_key (
                email VARCHAR(191) NOT NULL,
                phone VARCHAR(32) NULL,
                full_name VARCHAR(200) NULL
            ) COMMENT 'ATTENDU : aucune cle primaire ni index, et des lignes strictement identiques.'
            SQL);

        $db->fill('contacts_without_key', 50000, [
            'email' => "CONCAT('dup', MOD(n, 1000), '@nokey.example')",
            'phone' => "CONCAT('+336', LPAD(MOD(n, 1000), 8, '0'))",
            'full_name' => "ELT(1 + MOD(n, 3), 'Ana Roux', 'Bob Petit', NULL)",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE unique_without_primary (
                email VARCHAR(191) NOT NULL,
                national_id VARCHAR(20) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                UNIQUE KEY unique_without_primary_email_unique (email),
                UNIQUE KEY unique_without_primary_national_unique (national_id)
            ) COMMENT 'ATTENDU : pas de cle primaire ; MySQL utilise le premier index unique NOT NULL (email) comme cle.'
            SQL);

        $db->fill('unique_without_primary', 30000, [
            'email' => PiiSql::EMAIL,
            'national_id' => "CONCAT('ID', LPAD(n, 12, '0'))",
            'full_name' => PiiSql::FULL_NAME,
        ]);

        $created = $db->attempt('colonne invisible sur invisible_columns', <<<'SQL'
            CREATE TABLE invisible_columns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(200) NOT NULL,
                ssn CHAR(11) NOT NULL INVISIBLE,
                password VARCHAR(255) NOT NULL INVISIBLE
            ) COMMENT 'ATTENDU : fuite possible. ssn et password sont des colonnes INVISIBLES, absentes de SELECT *.'
            SQL);

        if ($created) {
            $db->fill('invisible_columns', 10000, [
                'full_name' => PiiSql::FULL_NAME,
                'ssn' => "CONCAT(LPAD(MOD(n, 1000), 3, '0'), '-', LPAD(MOD(n, 100), 2, '0'), '-', LPAD(MOD(n, 10000), 4, '0'))",
                'password' => PiiSql::PASSWORD_HASH,
            ]);
        }
    },
];
