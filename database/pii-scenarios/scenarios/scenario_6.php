<?php

declare(strict_types=1);

/**
 * Scénario 6 : noms de tables et de colonnes hostiles, valeurs extrêmes et types rares.
 */
return [
    'title' => 'Identifiants hostiles et valeurs extrêmes',
    'build' => static function (PiiScenarioBuilder $db): void {
        $db->run(<<<'SQL'
            CREATE TABLE `User Accounts` (
                `ID` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `Email` VARCHAR(191) NOT NULL,
                `EMAIL_ADDRESS` VARCHAR(191) NOT NULL,
                `firstName` VARCHAR(100) NOT NULL,
                `LastName` VARCHAR(100) NOT NULL,
                `dateOfBirth` DATE NULL,
                `PhoneNumber` VARCHAR(32) NULL,
                `phone number` VARCHAR(32) NULL,
                `e-mail` VARCHAR(191) NULL,
                `first-name` VARCHAR(100) NULL,
                `Password` VARCHAR(255) NOT NULL,
                `IP` VARCHAR(45) NULL,
                `ZIP-Code` VARCHAR(10) NULL,
                UNIQUE KEY `uq Email` (`Email`)
            ) COMMENT 'ATTENDU : succes si les identifiants sont bien echappes. Espace dans le nom de table, camelCase, majuscules, tirets et espaces dans les colonnes.'
            SQL);

        $db->fill('User Accounts', 30000, [
            'Email' => PiiSql::EMAIL,
            'EMAIL_ADDRESS' => "CONCAT('alt', n, '@corp.example')",
            'firstName' => PiiSql::FIRST_NAME,
            'LastName' => PiiSql::LAST_NAME,
            'dateOfBirth' => PiiSql::BIRTH_DATE,
            'PhoneNumber' => PiiSql::PHONE,
            'phone number' => PiiSql::PHONE,
            'e-mail' => "CONCAT('dash', n, '@corp.example')",
            'first-name' => PiiSql::FIRST_NAME,
            'Password' => PiiSql::PASSWORD_HASH,
            'IP' => PiiSql::IPV4,
            'ZIP-Code' => PiiSql::POSTCODE,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE `données_clients` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `prénom` VARCHAR(100) NOT NULL,
                `prenom` VARCHAR(100) NOT NULL,
                `nom` VARCHAR(100) NOT NULL,
                `téléphone` VARCHAR(32) NULL,
                `telephone` VARCHAR(32) NULL,
                `adresse` VARCHAR(255) NULL,
                `adresse_électronique` VARCHAR(191) NULL,
                `courriel` VARCHAR(191) NULL,
                `code_postal` VARCHAR(10) NULL,
                `ville` VARCHAR(100) NULL,
                `pays` CHAR(2) NULL,
                `date_de_naissance` DATE NULL,
                `mot_de_passe` VARCHAR(255) NULL,
                `société` VARCHAR(150) NULL,
                `numero_secu` VARCHAR(21) NULL,
                `immatriculation` VARCHAR(12) NULL
            ) COMMENT 'ATTENDU : fuite partielle. Noms francais avec accents (prénom, téléphone) non reconnus par les motifs sans accent.'
            SQL);

        $db->fill('données_clients', 30000, [
            'prénom' => PiiSql::FIRST_NAME,
            'prenom' => PiiSql::FIRST_NAME,
            'nom' => PiiSql::LAST_NAME,
            'téléphone' => PiiSql::PHONE,
            'telephone' => PiiSql::PHONE,
            'adresse' => PiiSql::STREET,
            'adresse_électronique' => PiiSql::EMAIL,
            'courriel' => PiiSql::EMAIL,
            'code_postal' => PiiSql::POSTCODE,
            'ville' => PiiSql::CITY,
            'pays' => PiiSql::COUNTRY,
            'date_de_naissance' => PiiSql::BIRTH_DATE,
            'mot_de_passe' => PiiSql::PASSWORD_HASH,
            'société' => PiiSql::COMPANY,
            'numero_secu' => "CONCAT('1 85 05 75 ', LPAD(MOD(n, 999), 3, '0'), ' ', LPAD(MOD(n, 999), 3, '0'), ' 42')",
            'immatriculation' => "CONCAT('AB-', LPAD(MOD(n, 999), 3, '0'), '-CD')",
        ]);

        foreach (['null', 'true', 'false', 'yes', 'no', 'on', '123', '1.5e3', 'tables', 'columns', 'strategy'] as $table) {
            $db->run('CREATE TABLE '.PiiScenarioBuilder::quote($table).' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) NOT NULL,
                name VARCHAR(200) NOT NULL
            ) COMMENT \'ATTENDU : le nom de table est un mot que YAML interprete (null, booleen, nombre) ou une cle du fichier de clonage.\'');

            $db->fill($table, 500, [
                'email' => PiiSql::EMAIL,
                'name' => PiiSql::FULL_NAME,
            ]);
        }

        $db->run(<<<'SQL'
            CREATE TABLE yaml_hostile_columns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `null` VARCHAR(50) NULL,
                `true` VARCHAR(50) NULL,
                `false` VARCHAR(50) NULL,
                `yes` VARCHAR(50) NULL,
                `no` VARCHAR(50) NULL,
                `on` VARCHAR(50) NULL,
                `off` VARCHAR(50) NULL,
                `y` VARCHAR(50) NULL,
                `n` VARCHAR(50) NULL,
                `~` VARCHAR(50) NULL,
                `123` VARCHAR(50) NULL,
                `1e3` VARCHAR(50) NULL,
                `0x1F` VARCHAR(50) NULL,
                `2024-01-01` VARCHAR(50) NULL,
                `strategy` VARCHAR(50) NULL,
                `columns` VARCHAR(50) NULL,
                `rows` VARCHAR(50) NULL,
                `key: value` VARCHAR(50) NULL,
                `a # comment` VARCHAR(50) NULL,
                `it's` VARCHAR(50) NULL,
                `say "hi"` VARCHAR(50) NULL,
                `{braces}` VARCHAR(50) NULL,
                `[brackets]` VARCHAR(50) NULL,
                `*star` VARCHAR(50) NULL,
                `&anchor` VARCHAR(50) NULL,
                `!tag` VARCHAR(50) NULL,
                `%percent` VARCHAR(50) NULL,
                `@at` VARCHAR(50) NULL,
                `back``tick` VARCHAR(50) NULL,
                `- dash` VARCHAR(50) NULL,
                `? question` VARCHAR(50) NULL,
                `| pipe` VARCHAR(50) NULL,
                `> fold` VARCHAR(50) NULL,
                `email` VARCHAR(191) NOT NULL,
                `session_id` CHAR(40) NULL
            ) COMMENT 'ATTENDU : chaque nom de colonne doit etre mis entre guillemets dans le YAML genere (null, booleens, nombres, caracteres reserves).'
            SQL);

        $db->fill('yaml_hostile_columns', 2000, [
            'null' => "'null'",
            'true' => "'true'",
            'false' => "'false'",
            'yes' => "'yes'",
            'no' => "'no'",
            '123' => "'123'",
            'strategy' => "'keep'",
            'key: value' => "CONCAT('v', n)",
            "it's" => "CONCAT('v', n)",
            'email' => PiiSql::EMAIL,
            'session_id' => PiiSql::SHA1,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE reserved_words (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(64) NOT NULL,
                `order` INT NOT NULL,
                `group` VARCHAR(50) NOT NULL,
                `select` VARCHAR(50) NULL,
                `from` VARCHAR(191) NULL,
                `where` VARCHAR(100) NULL,
                `index` INT NULL,
                `desc` TEXT NULL,
                `user` VARCHAR(100) NULL,
                `password` VARCHAR(255) NULL,
                `name` VARCHAR(200) NULL,
                `condition` VARCHAR(50) NULL,
                `status` VARCHAR(20) NOT NULL
            ) COMMENT 'ATTENDU : succes. Noms de colonnes qui sont des mots reserves SQL.'
            SQL);

        $db->fill('reserved_words', 20000, [
            'key' => PiiSql::MD5,
            'order' => 'MOD(n, 100)',
            'group' => "ELT(1 + MOD(n, 3), 'a', 'b', 'c')",
            'select' => "CONCAT('choice-', MOD(n, 9))",
            'from' => PiiSql::EMAIL,
            'where' => PiiSql::CITY,
            'index' => 'n',
            'desc' => "CONCAT('Description ', n)",
            'user' => PiiSql::USERNAME,
            'password' => PiiSql::PASSWORD_HASH,
            'name' => PiiSql::FULL_NAME,
            'condition' => "ELT(1 + MOD(n, 2), 'new', 'used')",
            'status' => "ELT(1 + MOD(n, 2), 'active', 'inactive')",
        ]);

        $db->run('CREATE TABLE `Contacts` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(191) NOT NULL, phone VARCHAR(32) NULL) COMMENT \'ATTENDU : deux tables ne different que par la casse (Contacts et contacts).\'');
        $db->fill('Contacts', 5000, ['email' => PiiSql::EMAIL, 'phone' => PiiSql::PHONE]);

        if ($db->attempt('seconde table differente par la casse (contacts)', 'CREATE TABLE `contacts` (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(191) NOT NULL, mobile VARCHAR(32) NULL) COMMENT \'ATTENDU : homonyme en minuscules de Contacts.\'')) {
            $db->fill('contacts', 5000, ['email' => "CONCAT('lower', n, '@corp.example')", 'mobile' => PiiSql::PHONE]);
        }

        $longName = str_pad('table_dont_le_nom_atteint_la_limite_de_64_caracteres', 64, '_x');
        $longColumn = str_pad('email_dont_le_nom_atteint_la_limite_de_64_caracteres', 64, '_y');

        $db->run('CREATE TABLE '.PiiScenarioBuilder::quote($longName).' (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            '.PiiScenarioBuilder::quote($longColumn).' VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL
        ) COMMENT \'ATTENDU : succes. Noms de table et de colonne a la longueur maximale de 64 caracteres.\'');

        $db->fill($longName, 2000, [$longColumn => PiiSql::EMAIL, 'email' => PiiSql::EMAIL]);

        $db->run(<<<'SQL'
            CREATE TABLE `顧客` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `名前` VARCHAR(100) NOT NULL,
                `メール` VARCHAR(191) NOT NULL,
                email VARCHAR(191) NOT NULL,
                phone VARCHAR(32) NULL
            ) COMMENT 'ATTENDU : succes. Nom de table et de colonnes en japonais.'
            SQL);

        $db->fill('顧客', 5000, [
            '名前' => "ELT(1 + MOD(n, 3), '山田太郎', '佐藤花子', '鈴木一郎')",
            'メール' => PiiSql::EMAIL,
            'email' => PiiSql::EMAIL,
            'phone' => PiiSql::PHONE,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE extreme_values (
                id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                email VARCHAR(320) NOT NULL,
                full_name VARCHAR(500) NOT NULL,
                username VARCHAR(191) NOT NULL,
                phone VARCHAR(64) NOT NULL,
                street MEDIUMTEXT NOT NULL,
                city VARCHAR(191) NOT NULL,
                age INT NULL,
                latitude DECIMAL(10, 7) NULL,
                birth_date DATE NULL,
                card_number VARCHAR(64) NULL,
                password VARCHAR(255) NULL,
                UNIQUE KEY extreme_values_username_unique (username)
            ) COMMENT 'ATTENDU : succes. Valeurs sources extremes : emojis, RTL, texte tres long, guillemets, antislash, octet nul, chaines NULL et \\N, bornes numeriques et de dates.'
            SQL);

        $db->fill('extreme_values', 20000, [
            'id' => 'IF(n = 1, 18446744073709551615, IF(n = 2, 9223372036854775807, n))',
            'email' => "CASE MOD(n, 8)
                WHEN 0 THEN CONCAT(REPEAT('a', 64), n, '@', REPEAT('b', 180), '.example')
                WHEN 1 THEN CONCAT('\"quoted', n, '\"@corp.example')
                WHEN 2 THEN CONCAT('o''brien', n, '@corp.example')
                WHEN 3 THEN CONCAT('ünïcödé', n, '@exämple.fr')
                WHEN 4 THEN CONCAT('emoji😀', n, '@corp.example')
                WHEN 5 THEN ''
                WHEN 6 THEN 'NULL'
                ELSE CONCAT('user+tag', n, '@corp.example') END",
            'full_name' => "CASE MOD(n, 10)
                WHEN 0 THEN CONCAT('😀🎉 ', n)
                WHEN 1 THEN CONCAT('محمد علي ', n)
                WHEN 2 THEN CONCAT('山田 太郎 ', n)
                WHEN 3 THEN CONCAT('O''Connor; DROP TABLE users;-- ', n)
                WHEN 4 THEN CONCAT('Back\\\\slash \\\\N ', n)
                WHEN 5 THEN CONCAT('Line1\\nLine2\\tTab ', n)
                WHEN 6 THEN CONCAT('Nul', CHAR(0 USING utf8mb4), 'byte ', n)
                WHEN 7 THEN REPEAT('x', 500)
                WHEN 8 THEN ''
                ELSE CONCAT('\"double\" ''single'' `tick` ', n) END",
            'username' => "CONCAT(ELT(1 + MOD(n, 5), 'NULL', 'null', '\\\\N', 'true', ''), n)",
            'phone' => "ELT(1 + MOD(n, 5), '+33 (0)6 12 34 56 78', '0033612345678', '06.12.34.56.78', 'N/A', '')",
            'street' => 'IF(MOD(n, 2000) = 0, REPEAT(CONCAT(MD5(n), \' \'), 30000), '.PiiSql::STREET.')',
            'city' => "ELT(1 + MOD(n, 4), 'Saint-Rémy-en-Bouzemont-Saint-Genest-et-Isson', 'Y', 'L''Haÿ-les-Roses', 'Ærøskøbing')",
            'age' => 'ELT(1 + MOD(n, 6), -1, 0, 150, 2147483647, -2147483648, NULL)',
            'latitude' => 'ELT(1 + MOD(n, 5), -90, 90, 0, 48.8566140, NULL)',
            'birth_date' => "ELT(1 + MOD(n, 5), '1000-01-01', '9999-12-31', '1900-02-28', '2024-02-29', NULL)",
            'card_number' => "ELT(1 + MOD(n, 4), '4111 1111 1111 1111', '4111-1111-1111-1111', '****-****-****-1111', NULL)",
            'password' => "ELT(1 + MOD(n, 4), 'plaintext', MD5(n), '', NULL)",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE large_documents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(200) NOT NULL,
                email VARCHAR(191) NOT NULL,
                avatar LONGBLOB NULL,
                biography LONGTEXT NULL,
                profile JSON NULL,
                signature MEDIUMBLOB NULL
            ) COMMENT 'ATTENDU : succes mais charge memoire. Lignes de plusieurs megaoctets (LONGBLOB, LONGTEXT) et JSON imbrique.'
            SQL);

        $db->fill('large_documents', 80, [
            'full_name' => PiiSql::FULL_NAME,
            'email' => PiiSql::EMAIL,
            'avatar' => 'REPEAT(RANDOM_BYTES(1024), 1024 + MOD(n, 1024))',
            'biography' => 'REPEAT(CONCAT(SHA2(n, 512), \' \'), 8000)',
            'profile' => "JSON_OBJECT('email', CONCAT('json', n, '@corp.example'), 'phones', JSON_ARRAY('+33600000001', '+33600000002'), 'address', JSON_OBJECT('street', '1 rue X', 'geo', JSON_OBJECT('lat', 48.85, 'lng', 2.35)), 'ssn', '123-45-6789')",
            'signature' => 'RANDOM_BYTES(1024)',
        ], 20);

        $columns = [];
        $values = [];
        foreach (['work', 'home', 'billing', 'backup', 'recovery', 'parent', 'spouse', 'manager', 'assistant', 'legacy'] as $prefix) {
            foreach (range(1, 6) as $index) {
                $columns[] = "{$prefix}_{$index}_email VARCHAR(100) NULL";
                $values["{$prefix}_{$index}_email"] = "CONCAT('{$prefix}{$index}-', n, '@corp.example')";
                $columns[] = "{$prefix}_{$index}_ip VARCHAR(45) CHARACTER SET ascii NULL";
                $values["{$prefix}_{$index}_ip"] = PiiSql::IPV4;
            }
        }
        foreach (range(1, 40) as $index) {
            $columns[] = "attribute_{$index} VARCHAR(30) CHARACTER SET ascii NULL";
            $values["attribute_{$index}"] = "CONCAT('valeur ', MOD(n, {$index} + 1))";
        }

        $db->run("CREATE TABLE wide_table (\n    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n    ".implode(",\n    ", $columns)."\n) COMMENT 'ATTENDU : succes. 161 colonnes dont 60 emails et 60 adresses IP reconnus par les jokers *_email et *_ip.'");
        $db->fill('wide_table', 5000, $values, 1000);

        $db->run(<<<'SQL'
            CREATE TABLE rare_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) NOT NULL,
                coordinates POINT NOT NULL SRID 4326,
                home_area POLYGON NULL,
                is_verified BIT(1) NOT NULL,
                permissions SET('read', 'write', 'admin') NOT NULL,
                born_year YEAR NULL,
                wake_time TIME(3) NULL,
                last_seen TIMESTAMP(6) NULL,
                external_id BINARY(16) NOT NULL,
                price DECIMAL(65, 30) NULL,
                ratio DOUBLE NULL,
                tiny_flag TINYINT(1) NOT NULL DEFAULT 0,
                generated_uuid CHAR(36) NOT NULL DEFAULT (UUID()),
                SPATIAL KEY rare_types_coordinates_spatial (coordinates),
                FULLTEXT KEY rare_types_email_fulltext (email)
            ) COMMENT 'ATTENDU : succes. Types rares : POINT avec SRID, POLYGON, BIT, SET, YEAR, TIME(3), TIMESTAMP(6), DECIMAL(65,30), valeur par defaut calculee, index spatial et plein texte.'
            SQL);

        $db->fill('rare_types', 20000, [
            'email' => PiiSql::EMAIL,
            'coordinates' => "ST_PointFromText(CONCAT('POINT(', ".PiiSql::LATITUDE.", ' ', ".PiiSql::LONGITUDE.", ')'), 4326)",
            'home_area' => "IF(MOD(n, 3) = 0, NULL, ST_GeomFromText('POLYGON((0 0, 0 1, 1 1, 1 0, 0 0))'))",
            'is_verified' => "IF(MOD(n, 2) = 0, b'1', b'0')",
            'permissions' => "ELT(1 + MOD(n, 3), 'read', 'read,write', 'read,write,admin')",
            'born_year' => '1950 + MOD(n, 60)',
            'wake_time' => 'SEC_TO_TIME(MOD(n * 61, 86400) + 0.123)',
            'last_seen' => "DATE_ADD('2024-01-01 00:00:00.123456', INTERVAL MOD(n * 7919, 60000000) SECOND)",
            'external_id' => 'UNHEX(MD5(n))',
            'price' => 'n / 7',
            'ratio' => 'n / 3',
            'tiny_flag' => 'MOD(n, 2)',
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE legacy_myisam_latin1 (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                city VARCHAR(50) NOT NULL,
                phone VARCHAR(20) NULL,
                UNIQUE KEY legacy_email_unique (email)
            ) ENGINE = MyISAM DEFAULT CHARSET = latin1 COMMENT = 'ATTENDU : succes en fr_FR. Table MyISAM en latin1 : ni transaction ni cle etrangere, accents limites a latin1.'
            SQL);

        $db->fill('legacy_myisam_latin1', 30000, [
            'email' => PiiSql::EMAIL,
            'first_name' => PiiSql::FIRST_NAME,
            'last_name' => PiiSql::LAST_NAME,
            'city' => PiiSql::CITY,
            'phone' => PiiSql::PHONE,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE empty_table (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                UNIQUE KEY empty_table_email_unique (email)
            ) COMMENT 'ATTENDU : succes. Table sans aucune ligne.'
            SQL);

        $db->run(<<<'SQL'
            CREATE TABLE single_column (
                email VARCHAR(191) NOT NULL PRIMARY KEY
            ) COMMENT 'ATTENDU : table d une seule colonne, qui est a la fois la cle primaire et une donnee personnelle.'
            SQL);

        $db->fill('single_column', 20000, ['email' => PiiSql::EMAIL]);
    },
];
