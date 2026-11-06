Sujet : 1. Conception et implémentation d'un agent IA conversationnel privacy-first pour la gestion et la manipulation des bases de données SQL relationnelles.

Features :
1. create-project-form
- [x] Clone Database
- [ ] Load databases to select
- [ ] In memory sqlite connexion
- [ ] Add start prompt input when creating a new project(Qui sert à demander à l'utilisateur de fournir un prompt détaillé(avec ou pas d'image de chema de la base de données) pour que l'agent puisse créer une BDD et les tables à partir des données de connexion)
- [ ] Add create_database_if_not_exists option to create a new database if it does not exist on cloning

2. Prompt input
- [ ] Add file mention to prompt input by typing @

⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️
- PHP extensions : pdo_sqlite, pdo_mysql, pdo_pgsql pdo_sqlite, mbstring
- Ne pas oublier de configurer /etc/sudoers.d/agent-driver-install sous Linux, Task Scheduler sous Windows l'autorisation root/Admin
et ainsi avoir la possibilité d'installer mysql, sqlite3, postgresql ou mariadb sur le système de l'utilisateur.
- Toujous lors de l'installation, configurer le fichier `~/.config/opendb/.env` pour y stocker des variables d'env
comme `APP_KEY=base64:xK9mP...` qui servira de clé d'encryptage pour les clés informations sensibles comme (mot de passe
de la base de données, clé API, etc.)
- Configure le fichier d'env de l'app en prod :
```php
    ->registered(function (Application $app): void {
        $app->useEnvironmentPath(base_path: dirname(__DIR__));
    })
```
- Configure php.ini
 - upload_max_filesize: 15M
 - post_max_size: 15M

- Liste noire de champs et des tables à ne jamais exposer aux LLM
⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️


A faire : Annulation d'une réponse d'un agent, Hors périmètre : requêtes paramétrées, édition des cellules du résultat, graphiques, annulation d'une requête, transactions manuelles.
