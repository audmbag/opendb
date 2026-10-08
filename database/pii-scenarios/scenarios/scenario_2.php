<?php

declare(strict_types=1);

/**
 * Scénario 2 : une colonne unique par générateur, à grand volume. Chaque table isole un risque de doublon.
 */
return [
    'title' => 'Index uniques sous pression',
    'build' => static function (PiiScenarioBuilder $db): void {
        /**
         * @param  array<string, string>  $columns  Nom => définition SQL de la colonne unique
         * @param  array<string, string>  $values  Nom => expression SQL de la valeur source (unique)
         */
        $uniqueTable = static function (string $table, int $rows, array $columns, array $values, string $comment, array $extraKeys = []) use ($db): void {
            $definitions = [];
            $keys = $extraKeys;
            foreach ($columns as $name => $definition) {
                $definitions[] = PiiScenarioBuilder::quote($name).' '.$definition;
                if ($extraKeys === []) {
                    $keys[] = 'UNIQUE KEY '.PiiScenarioBuilder::quote("{$table}_{$name}_unique").' ('.PiiScenarioBuilder::quote($name).')';
                }
            }

            $db->run('CREATE TABLE '.PiiScenarioBuilder::quote($table)." (\n    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    "
                .implode(",\n    ", [...$definitions, ...$keys])."\n) COMMENT '".str_replace("'", "''", $comment)."'");

            $db->fill($table, $rows, $values);
        };

        $uniqueTable('unique_emails', 150000,
            ['email' => 'VARCHAR(255) NOT NULL'],
            ['email' => PiiSql::EMAIL],
            'ATTENDU : succes. regexify sur 12 lettres, doublon improbable sur 150 000 lignes.');

        $uniqueTable('unique_usernames', 150000,
            ['username' => 'VARCHAR(30) NOT NULL'],
            ['username' => PiiSql::USERNAME],
            'ATTENDU : succes. 8 lettres + 4 chiffres, doublon improbable.');

        $uniqueTable('unique_phones', 120000,
            ['phone' => 'VARCHAR(20) NOT NULL'],
            ['phone' => PiiSql::PHONE],
            'ATTENDU : echec probable. e164PhoneNumber ne couvre qu environ un milliard de valeurs en fr_FR.');

        $uniqueTable('unique_person_names', 60000,
            ['first_name' => 'VARCHAR(60) NOT NULL', 'last_name' => 'VARCHAR(60) NOT NULL'],
            ['first_name' => "CONCAT('Prenom', n)", 'last_name' => "CONCAT('Nom', MOD(n, 1000))"],
            'ATTENDU : echec. Index unique compose (first_name, last_name) : Faker a trop peu de combinaisons.',
            ['UNIQUE KEY unique_person_names_pair_unique (first_name, last_name)']);

        $uniqueTable('unique_names', 60000,
            ['name' => 'VARCHAR(120) NOT NULL'],
            ['name' => "CONCAT('Role ', n)"],
            'ATTENDU : echec. Colonne name unique (type roles.name) remplacee par des noms de personnes.');

        $uniqueTable('unique_ssn', 100000,
            ['ssn' => 'CHAR(11) NOT NULL'],
            ['ssn' => "CONCAT(LPAD(MOD(n, 1000), 3, '0'), '-', LPAD(MOD(FLOOR(n / 1000), 100), 2, '0'), '-', LPAD(FLOOR(n / 100000), 4, '0'))"],
            'ATTENDU : echec quasi certain. numerify ###-##-#### : un milliard de valeurs pour 100 000 lignes.');

        $uniqueTable('unique_passports', 100000,
            ['passport_number' => 'CHAR(9) NOT NULL'],
            ['passport_number' => "CONCAT('P', LPAD(n, 8, '0'))"],
            'ATTENDU : echec probable. Une lettre + 8 chiffres : 2,6 milliards de valeurs.');

        $uniqueTable('unique_cards', 100000,
            ['card_number' => 'VARCHAR(19) NOT NULL'],
            ['card_number' => PiiSql::CARD_NUMBER],
            'ATTENDU : succes probable. creditCardNumber a un espace de valeurs tres large.');

        $uniqueTable('unique_ibans', 100000,
            ['iban' => 'VARCHAR(34) NOT NULL'],
            ['iban' => PiiSql::IBAN],
            'ATTENDU : succes probable.');

        $uniqueTable('unique_pins', 20000,
            ['pin' => 'CHAR(6) NOT NULL'],
            ['pin' => "LPAD(n, 6, '0')"],
            'ATTENDU : echec certain. numerify #### ne produit que 10 000 valeurs pour 20 000 lignes.');

        $uniqueTable('unique_ip_addresses', 150000,
            ['ip_address' => 'VARCHAR(45) NOT NULL'],
            ['ip_address' => PiiSql::IPV4],
            'ATTENDU : echec probable. ipv4 : 4,3 milliards de valeurs pour 150 000 lignes.');

        $uniqueTable('unique_mac_addresses', 100000,
            ['mac_address' => 'CHAR(17) NOT NULL'],
            ['mac_address' => PiiSql::MAC],
            'ATTENDU : succes probable.');

        $uniqueTable('unique_tokens', 200000,
            ['api_key' => 'CHAR(32) NOT NULL', 'access_token' => 'VARCHAR(64) NOT NULL', 'remember_token' => 'VARCHAR(100) NOT NULL'],
            ['api_key' => PiiSql::MD5, 'access_token' => PiiSql::SHA256, 'remember_token' => PiiSql::SHA1],
            'ATTENDU : succes. Trois colonnes de jetons uniques, regexify sur 32 caracteres hexadecimaux.');

        $uniqueTable('unique_devices', 200000,
            ['device_id' => 'CHAR(36) NOT NULL', 'cookie_id' => 'CHAR(36) NOT NULL', 'advertising_id' => 'CHAR(36) NOT NULL'],
            ['device_id' => PiiSql::UUID, 'cookie_id' => 'UUID()', 'advertising_id' => 'UUID()'],
            'ATTENDU : succes. Trois colonnes UUID uniques.');

        $uniqueTable('unique_postal_codes', 50000,
            ['postal_code' => 'VARCHAR(10) NOT NULL'],
            ['postal_code' => "LPAD(n, 6, '0')"],
            'ATTENDU : echec certain. Un code postal fr_FR n a que 100 000 valeurs possibles.');

        $uniqueTable('unique_cities', 30000,
            ['city' => 'VARCHAR(100) NOT NULL'],
            ['city' => "CONCAT('Ville-', n)"],
            'ATTENDU : echec. Faker ne connait pas 30 000 villes distinctes.');

        $uniqueTable('unique_companies', 30000,
            ['company' => 'VARCHAR(150) NOT NULL'],
            ['company' => "CONCAT('Societe ', n)"],
            'ATTENDU : echec probable.');

        $uniqueTable('unique_birth_dates', 20000,
            ['birth_date' => 'DATE NOT NULL'],
            ['birth_date' => "DATE_ADD('1940-01-01', INTERVAL n DAY)"],
            'ATTENDU : echec certain. Moins de 14 000 dates possibles entre 1970 et il y a 18 ans.');

        $uniqueTable('unique_vins', 100000,
            ['vin' => 'CHAR(17) NOT NULL', 'license_plate' => 'VARCHAR(12) NOT NULL'],
            ['vin' => "CONCAT('VF1', LPAD(n, 14, '0'))", 'license_plate' => "CONCAT('PL-', LPAD(n, 8, '0'))"],
            'ATTENDU : echec sur license_plate (175 millions de valeurs), succes sur vin.');

        $uniqueTable('unique_session_integers', 50000,
            ['session_id' => 'BIGINT UNSIGNED NOT NULL'],
            ['session_id' => '9000000000 + n'],
            'ATTENDU : echec. Entier NOT NULL unique vise par un matcher en strategie null : le repli numberBetween 0-100 cree des doublons.');

        $uniqueTable('unique_stripe_integers', 50000,
            ['stripe_id' => 'INT UNSIGNED NOT NULL'],
            ['stripe_id' => '1000000 + n'],
            'ATTENDU : echec. Meme cas avec un identifiant de prestataire de paiement stocke en entier.');

        $db->run(<<<'SQL'
            CREATE TABLE unique_email_prefix (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                UNIQUE KEY unique_email_prefix_unique (email(4))
            ) COMMENT 'ATTENDU : echec certain. Index unique sur les 4 premiers caracteres de email.'
            SQL);

        $db->fill('unique_email_prefix', 30000, [
            'email' => "CONCAT(LPAD(LOWER(CONV(n, 10, 36)), 4, '0'), '@prefix.example')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE unique_case_sensitive (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(30) COLLATE utf8mb4_bin NOT NULL,
                email VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
                UNIQUE KEY unique_case_sensitive_username_unique (username),
                UNIQUE KEY unique_case_sensitive_email_unique (email)
            ) COMMENT 'ATTENDU : succes. La source contient des valeurs qui ne different que par la casse (User1 / user1).'
            SQL);

        $db->fill('unique_case_sensitive', 40000, [
            'username' => "IF(MOD(n, 2) = 0, CONCAT('User', FLOOR(n / 2)), CONCAT('user', FLOOR(n / 2)))",
            'email' => "IF(MOD(n, 2) = 0, CONCAT('Mail', FLOOR(n / 2), '@Case.example'), CONCAT('mail', FLOOR(n / 2), '@case.example'))",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE unique_nullable (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NULL,
                phone VARCHAR(20) NULL,
                session_id CHAR(40) NULL,
                UNIQUE KEY unique_nullable_email_unique (email),
                UNIQUE KEY unique_nullable_phone_unique (phone),
                UNIQUE KEY unique_nullable_session_unique (session_id)
            ) COMMENT 'ATTENDU : succes sur session_id (null autorise en doublon). 80 % des lignes sont deja NULL.'
            SQL);

        $db->fill('unique_nullable', 100000, [
            'email' => 'IF(MOD(n, 5) = 0, '.PiiSql::EMAIL.', NULL)',
            'phone' => 'IF(MOD(n, 5) = 1, '.PiiSql::PHONE.', NULL)',
            'session_id' => 'IF(MOD(n, 5) = 2, '.PiiSql::SHA1.', NULL)',
        ]);
    },
];
