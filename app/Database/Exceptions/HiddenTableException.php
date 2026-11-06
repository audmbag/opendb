<?php

namespace App\Database\Exceptions;

use RuntimeException;

/**
 * A table the user disabled for the agent in the settings of the project, or a query that could reach one.
 */
class HiddenTableException extends RuntimeException
{
    private const string ASK_USER = 'Explique-le à l\'utilisateur et, si sa demande en a besoin, propose-lui de rendre la table accessible '
        .'dans les paramètres du projet (section « Tables », accès de l\'agent IA). N\'essaie pas de contourner cette restriction.';

    public static function named(string $table): self
    {
        return new self("La table [{$table}] est désactivée pour l'agent dans les paramètres du projet : "
            .'tu ne peux ni lire sa structure ou ses données, ni la modifier. '.self::ASK_USER);
    }

    public static function citedBy(string $table): self
    {
        return new self("Requête rejetée : elle cite la table [{$table}], désactivée pour l'agent dans les paramètres du projet. "
            .'Un nom de colonne, un alias ou une valeur identique au nom de la table suffit à rejeter la requête. '.self::ASK_USER);
    }

    public static function systemCatalog(): self
    {
        return new self('Requête rejetée : les catalogues système (information_schema, pg_catalog, sqlite_master…) sont inaccessibles '
            .'tant que des tables sont désactivées pour l\'agent, car ils décrivent aussi ces tables. '
            .'Utilise les tools `ListTables` et `FocusTable` pour explorer la structure de la base.');
    }

    public static function dynamicSql(): self
    {
        return new self('Requête rejetée : le SQL dynamique (PREPARE, EXECUTE, fonctions qui exécutent une requête passée en texte…) '
            .'est interdit tant que des tables sont désactivées pour l\'agent, car il permettrait de les atteindre sans les nommer. '
            .'Écris la requête directement.');
    }
}
