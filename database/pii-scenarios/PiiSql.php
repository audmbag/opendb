<?php

declare(strict_types=1);

/**
 * Expressions SQL déterministes, calculées à partir de l'entier `n` de la suite générée par PiiScenarioBuilder::fill().
 */
final class PiiSql
{
    public const string FIRST_NAME = "ELT(1 + MOD(n, 24), 'Alice', 'Bruno', 'Chloé', 'David', 'Emma', 'Farid', 'Gaëlle', 'Hugo', 'Inès', 'Jules', 'Karim', 'Léa', 'Marc', 'Nora', 'Omar', 'Paul', 'Quentin', 'Rose', 'Sami', 'Théo', 'Ugo', 'Vera', 'William', 'Zoé')";

    public const string LAST_NAME = "ELT(1 + MOD(FLOOR(n / 24), 24), 'Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Michel', 'Garcia', 'David', 'Bertrand', 'Roux', 'Vincent', 'Fournier', 'Morel', 'Girard', 'André', 'Mercier')";

    public const string FULL_NAME = 'CONCAT('.self::FIRST_NAME.", ' ', ".self::LAST_NAME.')';

    public const string EMAIL = "CONCAT('user', n, '@corp.example')";

    public const string USERNAME = "CONCAT('user_', n)";

    public const string PHONE = "CONCAT('+336', LPAD(n, 8, '0'))";

    public const string IPV4 = 'INET_NTOA(167772160 + n)';

    public const string MD5 = 'MD5(n)';

    public const string SHA1 = 'SHA1(n)';

    public const string SHA256 = 'SHA2(n, 256)';

    public const string UUID = "CONCAT_WS('-', SUBSTR(MD5(n), 1, 8), SUBSTR(MD5(n), 9, 4), CONCAT('4', SUBSTR(MD5(n), 14, 3)), CONCAT('a', SUBSTR(MD5(n), 18, 3)), SUBSTR(MD5(n), 21, 12))";

    public const string MAC = "UPPER(CONCAT_WS(':', SUBSTR(MD5(n), 1, 2), SUBSTR(MD5(n), 3, 2), SUBSTR(MD5(n), 5, 2), SUBSTR(MD5(n), 7, 2), SUBSTR(MD5(n), 9, 2), SUBSTR(MD5(n), 11, 2)))";

    public const string BIRTH_DATE = "DATE_ADD('1945-01-01', INTERVAL MOD(n * 37, 22000) DAY)";

    public const string CREATED_AT = "DATE_ADD('2020-01-01 00:00:00', INTERVAL MOD(n * 7919, 190000000) SECOND)";

    public const string CARD_NUMBER = "CONCAT('4', LPAD(n, 15, '0'))";

    public const string IBAN = "CONCAT('FR76', LPAD(n, 23, '0'))";

    public const string CITY = "ELT(1 + MOD(n, 12), 'Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Nantes', 'Strasbourg', 'Montpellier', 'Bordeaux', 'Lille', 'Rennes', 'Reims')";

    public const string COUNTRY = "ELT(1 + MOD(n, 6), 'FR', 'BE', 'CH', 'DE', 'ES', 'IT')";

    public const string POSTCODE = "LPAD(1000 + MOD(n * 13, 94000), 5, '0')";

    public const string STREET = "CONCAT(1 + MOD(n, 180), ' rue ', ELT(1 + MOD(n, 8), 'de la Paix', 'Victor Hugo', 'des Lilas', 'du Moulin', 'Pasteur', 'de la Gare', 'des Écoles', 'Nationale'))";

    public const string LATITUDE = 'ROUND(41 + MOD(n * 17, 1000) / 100, 6)';

    public const string LONGITUDE = 'ROUND(-5 + MOD(n * 29, 1400) / 100, 6)';

    public const string PASSWORD_HASH = "CONCAT('\$2y\$12\$', SUBSTR(SHA2(n, 512), 1, 53))";

    public const string COMPANY = "CONCAT(ELT(1 + MOD(n, 8), 'Dupont', 'Lambert', 'Rousseau', 'Faure', 'Blanc', 'Guerin', 'Muller', 'Henry'), ' ', ELT(1 + MOD(FLOOR(n / 8), 5), 'SARL', 'SAS', 'et Fils', 'Conseil', 'Industries'))";

    public const string JOB_TITLE = "ELT(1 + MOD(n, 8), 'Comptable', 'Développeur', 'Infirmier', 'Chef de projet', 'Vendeur', 'Juriste', 'Technicien', 'Directeur')";

    public const string USER_AGENT = "CONCAT('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/', 100 + MOD(n, 30), '.0 Safari/537.36')";
}
