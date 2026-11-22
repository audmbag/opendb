# OpenDB

**Talk to your database. It stays with you.**

OpenDB is an AI agent for MySQL, MariaDB, PostgreSQL and SQLite. Ask in plain language: it writes the SQL, answers
with a table or a chart, and waits for your approval before any write. It runs on your machine, with a local model
or your own API key.

**Website and documentation: [getopendb.cloud](https://getopendb.cloud)**

> [!NOTE]
> OpenDB is in beta. Please report what goes wrong in the [issues](https://github.com/audmbag/opendb/issues).

## Install

**Linux and macOS**

```sh
curl -fsSL https://github.com/audmbag/opendb/releases/latest/download/install.sh | sh
```

**Windows** (PowerShell)

```powershell
irm https://github.com/audmbag/opendb/releases/latest/download/install.ps1 | iex
```

Then run `opendb serve`, open <http://127.0.0.1:8000> and log in with `opendb` / `opendb` (change the password when
asked). Add an AI provider in **Settings → AI agent**, create a project and ask your first question.

## Development

You need PHP 8.5, [Composer](https://getcomposer.org) and [Bun](https://bun.sh).

```sh
git clone https://github.com/audmbag/opendb.git && cd opendb
composer install && bun install
cp .env.example .env && php artisan key:generate && php artisan migrate
composer run dev
```

Tests: `php artisan test --compact`.

## License

[MIT](LICENSE)
