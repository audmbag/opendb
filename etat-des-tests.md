# État des tests d'opendb

Relevé du 13 novembre 2026.

**1 794 tests, tous verts** (12 155 assertions). La suite tourne en 26 s en parallèle sur 8 processus, et en 90 s si on l'exécute d'un seul bloc. Toute la vérification se fait côté PHP : le front n'a aucun test.

## Outillage

- **Pest 4.7.8** avec `pest-plugin-laravel`. Mockery est disponible, mais presque pas utilisé (5 fois).
- **Deux suites** dans `phpunit.xml` : `Unit` et `Feature`.
- **L'environnement de test** :
  - la base de l'application est en SQLite `:memory:` ;
  - le cache, la session et le mail tournent en mémoire (`array`), la file d'attente est en `sync` ;
  - la langue est `fr` ;
  - `BCRYPT_ROUNDS=4` pour accélérer les tests ;
  - Pulse, Telescope et Nightwatch sont désactivés.
- **`tests/TestCase.php`** :
  - chaque requête envoie `Accept-Language: fr`, sinon Symfony force l'anglais ;
  - un helper `skipUnlessFortifyHas()` saute un test quand la fonctionnalité Fortify visée est désactivée.
- **`tests/Pest.php`** : `RefreshDatabase` ne s'applique qu'aux tests `Feature`. Ce fichier définit aussi des helpers partagés :
  - `projectWithSqliteDatabase()` : un projet branché sur un vrai fichier SQLite temporaire ;
  - `shopDatabase()` : une petite boutique avec 3 clients et 3 commandes ;
  - `conversationWithToolCall()` : une conversation en pause sur un appel d'outil ;
  - `chatProject()` et `openChat()` : pour piloter la commande `chat` ;
  - `queryPlanFixture()` : charge les plans EXPLAIN enregistrés.

## Répartition

| Domaine | Tests |
|---|---|
| Feature/Cloning (clonage, anonymisation, PII, tunnel SSH) | 651 |
| Unit (garde-fous SQL, parseurs de plans, SystemInfo…) | 368 |
| Feature à la racine (contrôleurs, commandes artisan, i18n, thèmes) | 295 |
| Feature/Ai (agents, outils, registre de modèles) | 156 |
| Feature/SqlEditor | 122 |
| Feature/Tables | 75 |
| Feature/Privileges | 52 |
| Feature/Mcp | 27 |
| Feature/Erd | 22 |
| Divers (Services, Workspace, Database, Auth) | 26 |
| **Total** | **1 794** |

Il y a 107 fichiers et environ 950 blocs `it()`. L'écart avec 1 794 vient des datasets (`->with()`, utilisé 139 fois), surtout dans le clonage.

## Ce qu'on simule, ce qu'on exécute vraiment

- **L'IA est toujours simulée** : aucun test n'appelle un vrai fournisseur. On utilise les `fake()` de laravel/ai : `SQLDatabaseAgent` (29 fois), `AnonymousAgent` (17), `QueryReviewerAgent`, `TableChatAgent`, etc.
- **Les autres simulations** :
  - `Http::fake` pour les listes de modèles des fournisseurs ;
  - `Process::fake`, `Storage::fake`, `Queue::fake` et `Mail::fake`.
- **Les bases cibles sont de vrais fichiers SQLite** : les outils de l'agent, l'éditeur SQL et l'explorateur de tables s'exécutent pour de vrai sur ces fichiers.
- **MySQL, MariaDB et PostgreSQL** ne sont testés qu'indirectement :
  - par 6 plans EXPLAIN enregistrés dans `tests/Fixtures/query-plans/` ;
  - par des tests unitaires purs (garde-fous, GRANT, découpage des requêtes).
- **Les commandes artisan** (`chat`, `settings`, `mcp`, comptes…) sont testées avec `expectsQuestion` / `expectsOutput`.

## Points faibles relevés

1. **Aucun test ne tourne sur un vrai serveur MySQL, MariaDB ou PostgreSQL.** L'application prétend pourtant prendre en charge ces quatre bases. Les différences entre bases (quoting, `information_schema`, transactions DDL, GRANT réels) ne sont donc pas couvertes.
2. **La CI (`.github/workflows/tests.yml`) a trois problèmes** :
   - elle ne se déclenche que sur les tags `v*` : les déclencheurs sur push et pull request sont en commentaire ;
   - sa matrice teste PHP 8.3, 8.4 et 8.5, alors que `composer.json` exige `^8.5` et que le code utilise `|>` et `array_first`. Les jobs 8.3 et 8.4 échoueront dès `composer install` ;
   - elle installe avec `npm i` et active la couverture xdebug sans jamais la produire.
   - `lint.yml` tourne lui aussi en PHP 8.4.
3. **Le front n'a aucun test** : ni Vitest ni tests navigateur Pest 4, seulement `eslint`, `tsc` et `prettier`.
4. **L'authentification est très peu testée** : `AuthenticationTest` ne vérifie que l'affichage de l'écran de connexion. Les tests d'accès aux projets sont en revanche solides (15 dans `ProjectAccessTest`).
5. **Il n'y a aucune mesure de couverture** : ni pcov ni xdebug ne sont installés en local.
6. **Il reste du code d'exemple** :
   - `tests/Unit/ExampleTest.php` (`true is true`) ;
   - dans `Pest.php`, l'expectation `toBeOne` et la fonction `something()`.
7. **Le test le plus lent** est `PiiMatchersFileTest` : environ 8 s sur les 90 de l'exécution d'un seul bloc, à cause de 4 cas qui génèrent beaucoup de valeurs. Ce n'est pas bloquant.

## Commandes utiles

- `vendor/bin/pest --parallel` : toute la suite.
- `php artisan test --compact --filter=…` : un test ciblé.
- `composer test` : vide la config, lance Pint en mode vérification, puis exécute les tests.
- `PAO_DISABLE=1 vendor/bin/pest …` : à utiliser si Pest ne sort rien. `laravel/pao` masque les erreurs fatales.

## Pistes

- Corriger la CI : ne tester que PHP 8.5, et la déclencher sur les push et pull requests.
- Ajouter une matrice MySQL, MariaDB et PostgreSQL via des services Docker.
