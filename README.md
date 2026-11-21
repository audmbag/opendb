# OpenDB

**Talk to your database. It stays with you.**

OpenDB is an AI agent that queries and updates MySQL, MariaDB, PostgreSQL and SQLite databases from plain-language
requests. It runs on your own machine. The model can run there too, and no write happens until you approve it.

> [!NOTE]
> OpenDB is in beta. Expect rough edges, and please report the ones you hit in the
> [issues](https://github.com/audmbag/opendb/issues).

## Features

- **Chat**: ask a question in plain language. The agent explores the schema, writes the SQL and answers with a table,
  key figures or a chart. Every write waits for your approval. The chat also runs in the terminal (`opendb chat`).
- **Tables**: browse and edit data in a grid, filter in plain language, design a table or import a file.
- **SQL editor**: write scripts in `.sql` files, run them, read the execution plan, and let the AI write, fix or
  optimize a query.
- **ERD schema**: see the tables and their relationships, spot schema issues, and describe a table for the AI to
  draft it.
- **MCP server**: other agents can use your OpenDB projects through the Model Context Protocol (`opendb mcp:serve`).
- **Users and roles**: share a project with other people and choose what each of them can do.

## Privacy

You choose where the model runs and what it can read.

- **Local models**: with [Ollama](https://ollama.com), the model runs on your machine or on your own server, and
  nothing leaves your infrastructure. Cloud providers can be used too, with your own API key.
- **Anonymized clones**: before a third-party model sees your data, OpenDB can copy the database and replace personal
  data column by column (fake values, hashes, masks, nulls). The agent then works on the copy.
- **Hidden tables**: you decide which tables the agent may read. The others don't exist for it.
- **Encrypted secrets**: database passwords and API keys are encrypted at rest with a key that stays on your machine.

## Install

OpenDB ships as a single self-contained binary: no PHP, web server or Node.js to install. The installers run without
administrator rights.

<!-- x-release-please-start-version -->

**Linux** (x86_64):

```sh
curl -fsSL https://github.com/audmbag/opendb/releases/download/v0.1.0-beta.4/install.sh | OPENDB_VERSION=v0.1.0-beta.4 sh
```

**Windows** (x86_64, PowerShell):

```powershell
$env:OPENDB_VERSION = 'v0.1.0-beta.4'; irm https://github.com/audmbag/opendb/releases/download/v0.1.0-beta.4/install.ps1 | iex
```

<!-- x-release-please-end -->

macOS builds (Apple Silicon and Intel) come with the next releases. You can also download an archive from the
[releases page](https://github.com/audmbag/opendb/releases) and check it against `SHA256SUMS`.

Then start the web interface:

```sh
opendb serve
```

Open <http://127.0.0.1:8000> and log in with the default account, `opendb` / `opendb`. OpenDB offers to change this
password on first login, which you should do.

OpenDB has no default model: connect a provider and enable a model in **Settings → AI agent**. Then create a
project, point it to a database, and ask your first question.

Other useful commands:

```sh
opendb app:help                # every command
opendb chat                    # the chat, in the terminal
opendb self-update --rollback  # go back to the previous version
```

Everything OpenDB writes (its configuration, its own database, logs) lives in `~/.opendb`.

## Development

OpenDB is a [Laravel](https://laravel.com) 13 application with an [Inertia](https://inertiajs.com) and React
front end. You need PHP 8.5 (with `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`, `mbstring` and `zip`),
[Composer](https://getcomposer.org) and [Bun](https://bun.sh).

```sh
git clone https://github.com/audmbag/opendb.git
cd opendb
composer install
bun install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

A checkout with its own `.env` keeps its data in the project folders. Without one, the application behaves as an
installed copy and writes to `~/.opendb`.

Run the tests and the checks:

```sh
php artisan test --compact
composer run ci:check
```

The binaries are built by `build/build.sh` (see the comments at the top of the script) and published by the release
workflow when a version tag is pushed.

## License

OpenDB is released under the [MIT License](LICENSE).
