<?php

declare(strict_types=1);

/**
 * Scénario 3 : les matchers en stratégie null face à des colonnes NOT NULL de tous types.
 */
return [
    'title' => 'Stratégie null sur colonnes NOT NULL',
    'build' => static function (PiiScenarioBuilder $db): void {
        $db->run(<<<'SQL'
            CREATE TABLE null_strings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gender VARCHAR(10) NOT NULL,
                marital_status VARCHAR(20) NOT NULL,
                religion VARCHAR(50) NOT NULL,
                race VARCHAR(50) NOT NULL,
                sexual_orientation VARCHAR(30) NOT NULL,
                political_opinion VARCHAR(50) NOT NULL,
                union_membership VARCHAR(50) NOT NULL,
                session_id CHAR(40) NOT NULL,
                blood_type VARCHAR(3) NOT NULL,
                diagnosis TEXT NOT NULL,
                allergies TEXT NOT NULL,
                medication VARCHAR(255) NOT NULL,
                disability VARCHAR(100) NOT NULL,
                avatar VARCHAR(255) NOT NULL,
                stripe_id VARCHAR(255) NOT NULL,
                dna LONGTEXT NOT NULL,
                fingerprint VARCHAR(64) NOT NULL
            ) COMMENT 'ATTENDU : succes avec repli. Chaque colonne est NOT NULL et visee par un matcher null ; blood_type est trop court pour REDACTED.'
            SQL);

        $db->fill('null_strings', 20000, [
            'gender' => "ELT(1 + MOD(n, 3), 'female', 'male', 'other')",
            'marital_status' => "ELT(1 + MOD(n, 4), 'single', 'married', 'divorced', 'widowed')",
            'religion' => "ELT(1 + MOD(n, 4), 'none', 'catholic', 'muslim', 'jewish')",
            'race' => "ELT(1 + MOD(n, 3), 'a', 'b', 'c')",
            'sexual_orientation' => "ELT(1 + MOD(n, 3), 'hetero', 'homo', 'bi')",
            'political_opinion' => "ELT(1 + MOD(n, 3), 'left', 'center', 'right')",
            'union_membership' => "ELT(1 + MOD(n, 3), 'CGT', 'CFDT', 'none')",
            'session_id' => PiiSql::SHA1,
            'blood_type' => "ELT(1 + MOD(n, 8), 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')",
            'diagnosis' => "CONCAT('Diagnostic numero ', n)",
            'allergies' => "ELT(1 + MOD(n, 3), 'pollen', 'arachide', 'aucune')",
            'medication' => "CONCAT('Medicament ', MOD(n, 500))",
            'disability' => "ELT(1 + MOD(n, 2), 'none', 'reduced mobility')",
            'avatar' => "CONCAT('avatars/', n, '.png')",
            'stripe_id' => "CONCAT('cus_', MD5(n))",
            'dna' => 'REPEAT(MD5(n), 20)',
            'fingerprint' => PiiSql::SHA256,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_unique_strings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id CHAR(40) NOT NULL,
                stripe_id VARCHAR(255) NOT NULL,
                fingerprint CHAR(64) NOT NULL,
                avatar VARCHAR(30) NOT NULL,
                UNIQUE KEY null_unique_strings_session_unique (session_id),
                UNIQUE KEY null_unique_strings_stripe_unique (stripe_id),
                UNIQUE KEY null_unique_strings_fingerprint_unique (fingerprint),
                UNIQUE KEY null_unique_strings_avatar_unique (avatar)
            ) COMMENT 'ATTENDU : repli par hachage sur les colonnes assez longues ; avatar (30 caracteres, unique) est le cas limite.'
            SQL);

        $db->fill('null_unique_strings', 20000, [
            'session_id' => PiiSql::SHA1,
            'stripe_id' => "CONCAT('cus_', MD5(n))",
            'fingerprint' => PiiSql::SHA256,
            'avatar' => "CONCAT('a/', n, '.png')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_numbers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gender TINYINT NOT NULL,
                marital_status SMALLINT NOT NULL,
                session_id BIGINT NOT NULL,
                religion INT NOT NULL,
                blood_type TINYINT UNSIGNED NOT NULL,
                disability TINYINT(1) NOT NULL,
                union_membership BIT(1) NOT NULL,
                stripe_id BIGINT UNSIGNED NOT NULL,
                diagnosis DECIMAL(6, 2) NOT NULL,
                avatar FLOAT NOT NULL
            ) COMMENT 'ATTENDU : succes avec repli numerique (0 ou false). Colonnes sensibles codees en nombres.'
            SQL);

        $db->fill('null_numbers', 20000, [
            'gender' => '1 + MOD(n, 3)',
            'marital_status' => '1 + MOD(n, 4)',
            'session_id' => '5000000000 + n',
            'religion' => '1 + MOD(n, 6)',
            'blood_type' => '1 + MOD(n, 8)',
            'disability' => 'MOD(n, 2)',
            'union_membership' => 'IF(MOD(n, 2) = 0, b\'1\', b\'0\')',
            'stripe_id' => '700000 + n',
            'diagnosis' => 'ROUND(MOD(n, 9000) / 10, 2)',
            'avatar' => 'n / 3',
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_enums (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gender ENUM('male', 'female', 'other') NOT NULL,
                marital_status ENUM('single', 'married', 'divorced', 'widowed') NOT NULL,
                blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
                religion SET('none', 'catholic', 'muslim', 'jewish', 'other') NOT NULL,
                disability ENUM('yes', 'no') NOT NULL DEFAULT 'no'
            ) COMMENT 'ATTENDU : aucune valeur de repli valide. ENUM et SET NOT NULL : null est refuse et REDACTED n est pas une valeur permise.'
            SQL);

        $db->fill('null_enums', 20000, [
            'gender' => "ELT(1 + MOD(n, 3), 'male', 'female', 'other')",
            'marital_status' => "ELT(1 + MOD(n, 4), 'single', 'married', 'divorced', 'widowed')",
            'blood_type' => "ELT(1 + MOD(n, 8), 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')",
            'religion' => "ELT(1 + MOD(n, 4), 'none', 'catholic', 'muslim,jewish', 'other')",
            'disability' => "ELT(1 + MOD(n, 2), 'yes', 'no')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_binary_json_dates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fingerprint BLOB NOT NULL,
                biometric VARBINARY(255) NOT NULL,
                face_id BINARY(32) NOT NULL,
                dna JSON NOT NULL,
                diagnosis JSON NOT NULL,
                avatar MEDIUMBLOB NOT NULL,
                session_id DATETIME NOT NULL,
                gender DATE NOT NULL,
                marital_status TIME NOT NULL,
                religion YEAR NOT NULL
            ) COMMENT 'ATTENDU : repli different par type. Binaire, JSON et dates NOT NULL sous des noms vises par un matcher null.'
            SQL);

        $db->fill('null_binary_json_dates', 10000, [
            'fingerprint' => 'RANDOM_BYTES(512)',
            'biometric' => 'RANDOM_BYTES(128)',
            'face_id' => 'UNHEX(SHA2(n, 256))',
            'dna' => "JSON_OBJECT('markers', JSON_ARRAY(n, n * 2, n * 3), 'lab', 'L1')",
            'diagnosis' => "JSON_OBJECT('code', CONCAT('A', MOD(n, 99)), 'chronic', MOD(n, 2) = 0)",
            'avatar' => 'RANDOM_BYTES(1024)',
            'session_id' => PiiSql::CREATED_AT,
            'gender' => PiiSql::BIRTH_DATE,
            'marital_status' => 'SEC_TO_TIME(MOD(n * 61, 86400))',
            'religion' => '1950 + MOD(n, 70)',
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_with_checks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gender CHAR(1) NOT NULL,
                marital_status VARCHAR(10) NOT NULL,
                session_id CHAR(40) NOT NULL,
                blood_type VARCHAR(3) NOT NULL,
                CONSTRAINT null_with_checks_gender CHECK (gender IN ('M', 'F', 'X')),
                CONSTRAINT null_with_checks_marital CHECK (marital_status IN ('single', 'married')),
                CONSTRAINT null_with_checks_session CHECK (CHAR_LENGTH(session_id) = 40),
                CONSTRAINT null_with_checks_blood CHECK (blood_type <> '')
            ) COMMENT 'ATTENDU : echec. Les valeurs de repli (REDACTED, chaine vide) violent les contraintes CHECK.'
            SQL);

        $db->fill('null_with_checks', 10000, [
            'gender' => "ELT(1 + MOD(n, 3), 'M', 'F', 'X')",
            'marital_status' => "ELT(1 + MOD(n, 2), 'single', 'married')",
            'session_id' => PiiSql::SHA1,
            'blood_type' => "ELT(1 + MOD(n, 4), 'A+', 'B+', 'AB+', 'O+')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE sessions (
                session_id CHAR(40) NOT NULL PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                created_at DATETIME NOT NULL
            ) COMMENT 'ATTENDU : session_id est la cle primaire, visee par un matcher null.'
            SQL);

        $db->fill('sessions', 20000, [
            'session_id' => PiiSql::SHA1,
            'ip_address' => PiiSql::IPV4,
            'created_at' => PiiSql::CREATED_AT,
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE session_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id CHAR(40) NOT NULL,
                event VARCHAR(50) NOT NULL,
                CONSTRAINT session_events_session_fk FOREIGN KEY (session_id) REFERENCES sessions (session_id)
            ) COMMENT 'ATTENDU : session_id est une cle etrangere NOT NULL vers sessions ; toute modification casse la relation.'
            SQL);

        $db->fill('session_events', 60000, [
            'session_id' => 'SHA1(1 + MOD(n, '.$db->rows(20000).'))',
            'event' => "ELT(1 + MOD(n, 3), 'view', 'click', 'purchase')",
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_with_defaults (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gender VARCHAR(10) NOT NULL DEFAULT 'unknown',
                marital_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
                session_id CHAR(40) NOT NULL DEFAULT '',
                blood_type TINYINT NOT NULL DEFAULT 0
            ) COMMENT 'ATTENDU : NOT NULL avec valeur par defaut. Un NULL explicite reste refuse par MySQL.'
            SQL);

        $db->fill('null_with_defaults', 10000, [
            'gender' => "ELT(1 + MOD(n, 2), 'female', 'male')",
            'marital_status' => "ELT(1 + MOD(n, 2), 'single', 'married')",
            'session_id' => PiiSql::SHA1,
            'blood_type' => '1 + MOD(n, 8)',
        ]);

        $db->run(<<<'SQL'
            CREATE TABLE null_nullable_control (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gender VARCHAR(10) NULL,
                marital_status VARCHAR(20) NULL,
                session_id CHAR(40) NULL,
                blood_type VARCHAR(3) NULL,
                diagnosis TEXT NULL,
                avatar VARCHAR(255) NULL,
                stripe_id VARCHAR(255) NULL,
                fingerprint BLOB NULL,
                dna JSON NULL,
                UNIQUE KEY null_nullable_control_session_unique (session_id),
                UNIQUE KEY null_nullable_control_stripe_unique (stripe_id)
            ) COMMENT 'ATTENDU : succes, tout passe a NULL. Table temoin : memes colonnes mais nullables, y compris sous index unique.'
            SQL);

        $db->fill('null_nullable_control', 20000, [
            'gender' => "ELT(1 + MOD(n, 2), 'female', 'male')",
            'marital_status' => "ELT(1 + MOD(n, 2), 'single', 'married')",
            'session_id' => PiiSql::SHA1,
            'blood_type' => "ELT(1 + MOD(n, 4), 'A+', 'B+', 'AB+', 'O+')",
            'diagnosis' => "CONCAT('Diagnostic ', n)",
            'avatar' => "CONCAT('avatars/', n, '.png')",
            'stripe_id' => "CONCAT('cus_', MD5(n))",
            'fingerprint' => 'RANDOM_BYTES(64)',
            'dna' => "JSON_OBJECT('n', n)",
        ]);
    },
];
