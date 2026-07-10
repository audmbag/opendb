<?php

namespace App\Console\Commands;

use App\Ai\Agents\SQLCoach;
use App\Services\CliMarkdown;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Output\OutputInterface;
use Termwind\Termwind;

use function Laravel\Prompts\{select, spin, stream, text, textarea};
use function Termwind\ask;
use function Termwind\render;
use function Termwind\terminal;

#[Signature('app:chat')]
#[Description('Agent that can chat with users and answer questions.')]
#[Help("This command starts a chat agent that can answer your questions about databases. You can ask anything related to databases, and the agent will do its best to help you. Type 'exit' to quit the chat.")]
class Chat extends Command
{
    const COMMANDS = [
        '/help',
        '/clear',
        '/exit',
        '/quit',
        '/mode',
    ];
    
    public string $mode = 'ask'; // or 'agent'

    public string $sessionId;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Prompt::cancelUsing(function () {
            $this->close();
        });

        $this->trap(SIGINT, function () {
            $this->close();
        });

        $this->header();

        exit(0);

        $count = 0;

        $this->sessionId = Str::random(16);

        do {
            $count++;

            $this->line($this->color('__________________________________________ '). $this->color(strtoupper($this->mode), "227") . $this->color(' ________________________________________________'));

            $this->newLine();
            // $input = text("\e[38;5;39mYou:\e[0m", placeholder: $this->getPlaceholder($count > 1), default: "Comment créer une table, soit super bref");
            $input = text("\e[38;5;39mYou:\e[0m", placeholder: $this->getPlaceholder($count > 1));

            $this->newLine();

            $this->detectCommands((string) $input);

        } while ($count);
    }

    public function detectCommands(string $input)
    {
        $input = strtolower(trim($input));

        if(str_starts_with($input, '/') && !in_array($input, self::COMMANDS)) {
            $this->error("Unknown command: {$input}");
            return;
        }
        
        switch($input) {
            case '/exit':
            case '/quit':
                $this->close();
                break;
            case '/help':
                $this->printHelp();
                break;
            case '/clear':
                terminal()->clear();
                break;
            case '/mode':
                $this->switchMode();
                break;
            default:
                $this->runChatAgent($input);
        }
    }


    public function switchMode()
    {
        $modes = ['ask', 'agent'];

        $mode = select("Select a mode:", $modes, default: $this->mode, hint: "Switch between 'ask' (Q&A) and 'agent' (tutor) modes.");

        $this->line($this->color("Switched to mode: {$mode}"));

        $this->mode = $mode;
    }


    public function runChatAgent(string $input, bool $fake = true)
    {
        spin(function () use ($input, $fake) {
            if($fake) {
                $chunks = $this->getFakeResponse();
            } else {
                $chunks = (new SQLCoach)->stream($input);
            }

            $stream = stream();

            foreach ($chunks as $chunk) {
                if($fake) {
                    $stream->append($chunk);
                    usleep(50000); 
                } else {
                    $stream->append(((array) $chunk)["delta"] ?? '');
                }
            }

            $stream->close();

            return "";
            
        }, "  Chat Agent is thinking...");
    }


    public function printHelp()
    {
        $this->line("<comment>SQL Expert CLI</comment> version <info>1.0.0</info>\n");

        // 2. Section USAGE
        $this->line("<comment>Usage:</comment>");
        $this->line("  command [options] [arguments]\n");

        // 3. Section OPTIONS
        $this->line("<comment>Options:</comment>");
        $this->line(sprintf("  %-20s %s", "<info>-h, --help</info>", "Display help for the given command."));
        $this->line(sprintf("  %-20s %s", "<info>-q, --quiet</info>", "Do not output any message."));
        $this->line("");

        // 4. Section COMMANDS (Le cœur de ton app)
        $this->line("<comment>Available Commands:</comment>");
        
        // Sous-catégorie Session
        $this->line(" <comment>session</comment>");
        $this->line(sprintf("  %-20s %s", "<info>/help</info>", "Display this help menu."));
        $this->line(sprintf("  %-20s %s", "<info>/mode [type]</info>", "Switch mode between <comment>ask</comment> (Q&A) and <comment>agent</comment> (tutor)."));
        $this->line(sprintf("  %-20s %s", "<info>/quit, /exit</info>", "Safely exit the interactive CLI session."));
        
        // Sous-catégorie History & Utils
        $this->line(" <comment>utils</comment>");
        $this->line(sprintf("  %-20s %s", "<info>/clear</info>", "Clear the terminal screen."));
        $this->line(sprintf("  %-20s %s", "<info>/history</info>", "Show the list of queries executed during this session."));
        $this->line("");

        // 5. Section PRO-TIP (Bandeau d'alerte style Laravel)
        $this->line("<question> INFO </question> Mode d'emploi : Pour les requêtes multi-lignes, l'IA traitera votre entrée lorsque vous sèmerez une ligne vide.");
        $this->line("");
    }

    public function color(string $text, string $color = "39"): string
    {
        return "\e[38;5;{$color}m{$text}\e[0m";
    }


    public function header()
    {
        echo "\e[2J\e[H\e[3J";

        $this->newLine();

        $this->printASCII([
            '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
            ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
            ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
            ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
            ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
            '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
        ]);

        $this->line("  Opendb - \e[38;5;213mVersion 1.0.0\e[0m");
        $this->line("  Hi, I'm your Opendb. I can help you with your database related questions and tasks. Let's chat!");

        $this->newLine();

        render(<<<HTML
            <div class="ml-2">
                <span class="px-1 bg-green-600"> Documentation</span>
                <a class="ml-1" href="https://laravel.com/docs/10.x/artisan" target="_blank">
                    https://laravel.com/docs/10.x/artisan
                </a>
            </div>
        HTML);

        $this->newLine();

        render(<<<HTML
            <div class="ml-2">
                <span class="pl-1 pr-1 bg-red-500">!</span> Please use the web interface for better experience by running <span class="px-1 text-blue-600">opendb web</span>
            </div>
        HTML);

        $this->newLine();

        render(<<<HTML
            <div class="ml-2">
                <div>  Type <span class="text-blue-400">/exit</span> to quit.</div>
                <div>  Type <span class="text-blue-400">/help</span> for more information.</div>
            </div>
        HTML);

        $this->newLine();
    }

    public function close()
    {
        terminal()->clear();

        $this->printASCII([
            '  ██████╗  ██████╗  ███████╗ ███╗   ██╗ ██████╗  ██████╗ ',
            ' ██╔═══██╗ ██╔══██╗ ██╔════╝ ████╗  ██║ ██╔══██╗ ██╔══██╗',
            ' ██║   ██║ ██████╔╝ █████╗   ██╔██╗ ██║ ██║  ██║ ██████╔╝',
            ' ██║   ██║ ██╔═══╝  ██╔══╝   ██║╚██╗██║ ██║  ██║ ██╔══██╗',
            ' ╚██████╔╝ ██║      ███████╗ ██║ ╚████║ ██████╔╝ ██████╔╝',
            '  ╚═════╝  ╚═╝      ╚══════╝ ╚═╝  ╚═══╝ ╚═════╝  ╚═════╝ ',
        ]);
        
        // $this->printASCII([
        //     '  ██████╗   ██████╗   ██████╗  ██████╗  ██████╗  ██╗   ██╗',
        //     ' ██╔════╝  ██╔═══██╗ ██╔═══██╗ ██╔══██╗ ██╔══██╗ ╚██╗ ██╔╝',
        //     ' ██║  ███╗ ██║   ██║ ██║   ██║ ██║  ██║ ██████╔╝  ╚████╔╝ ',
        //     ' ██║   ██║ ██║   ██║ ██║   ██║ ██║  ██║ ██╔══██╗   ╚██╔╝  ',
        //     ' ╚██████╔╝ ╚██████╔╝ ╚██████╔╝ ██████╔╝ ██████╔╝    ██║   ',
        //     '  ╚═════╝   ╚═════╝   ╚═════╝  ╚═════╝  ╚═════╝     ╚═╝   ',
        // ]);

        $this->line("  Opendb - \e[38;5;213mVersion 1.0.0\e[0m");

        $this->newLine();

        $this->info("  Goodbye!");

        $this->info("  Continue by running : \e[38;5;39mopendb --session={$this->sessionId}\e[0m");

        $this->newLine();

        exit(0);
    }


    public function printASCII(array $lines)
    {
        $gradient = [213, 177, 141, 105, 69, 39];

        foreach ($lines as $index => $line) {
            $color = $gradient[$index];
            $this->line("\e[38;5;{$color}m{$line}\e[0m");
        }
    }


    public function getPlaceholder(bool $isFirst): string
    {
        if($isFirst) {
            return "Ask me anything about your database...";
        }

        $prompts = [
            "What is the current status of my database?",
            "Can you help me optimize my database queries?",
            "How can I backup my database?",
            "What are the best practices for database security?",
            "Can you explain the concept of database normalization?",
        ];

        return $prompts[array_rand($prompts)];
    }


    public function getFakeResponse(): array
    {
        $response = <<<'MARKDOWN'
            # 🚀 Création de Tables en SQL
            
            Bienvenue dans le monde de la création de tables en SQL ! C'est l'une des premières et des plus fondamentales
            opérations que vous effectuerez.
            
            ## 🧱 Le Concept de Table
            
            Imaginez une table comme une feuille de calcul. Elle a des **colonnes** (qui définissent le type d'information
            stockée, comme "Nom", "Âge", "Date d'inscription") et des **lignes** (qui représentent des enregistrements
            individuels, comme une personne spécifique).
            
            ## 📜 La Syntaxe `CREATE TABLE`
            
            Pour créer une table, nous utilisons la commande `CREATE TABLE`. La syntaxe de base ressemble à ceci :
            
            ```sql
            CREATE TABLE nom_de_la_table (
                nom_colonne1 type_de_donnees [contraintes],
                nom_colonne2 type_de_donnees [contraintes],
                nom_colonne3 type_de_donnees [contraintes],
                ...
            );
            ```
            
            Décortiquons cela :
            
            -   `CREATE TABLE` : Les mots-clés SQL qui indiquent que vous voulez créer une nouvelle table.
            -   `nom_de_la_table` : Le nom que vous donnez à votre table. Choisissez des noms descriptifs et évitez les espaces.
            -   `()` : Les parenthèses encadrent la définition de vos colonnes.
            -   `nom_colonneX` : Le nom de chaque colonne. Encore une fois, des noms clairs sont essentiels.
            -   `type_de_donnees` : Le type d'information que la colonne peut contenir. Les plus courants sont :
                -   `INT` (ou `INTEGER`) : Pour les nombres entiers.
                -   `VARCHAR(n)` : Pour les chaînes de caractères de longueur variable (où `n` est la longueur maximale, par
            exemple `VARCHAR(255)`).
                -   `TEXT` : Pour les chaînes de caractères plus longues.
                -   `DATE` : Pour les dates (AAAA-MM-JJ).
                -   `BOOLEAN` : Pour les valeurs Vrai/Faux.
            -   `[contraintes]` : Des règles facultatives pour vos colonnes. Nous en verrons quelques-unes plus tard.
            
            ## 💡 Exemple Simple
            
            Créons une table simple pour stocker des informations sur des utilisateurs :
            
            ```sql
            CREATE TABLE utilisateurs (
                utilisateur_id INT,
                nom_utilisateur VARCHAR(50),
                email VARCHAR(100),
                date_creation DATE
            );
            ```
            
            Dans cet exemple :
            -   Nous avons une table nommée `utilisateurs`.
            -   Elle a quatre colonnes : `utilisateur_id` (un entier), `nom_utilisateur` (une chaîne de 50 caractères max),
            `email` (une chaîne de 100 caractères max) et `date_creation` (une date).
            
            ## 🔑 Les Contraintes Clés
            
            Les contraintes ajoutent de la logique et garantissent l'intégrité de vos données. Voici les plus importantes pour la
            création de table :
            
            -   `PRIMARY KEY` : Identifie de manière unique chaque ligne de la table. Une table ne devrait avoir qu'une seule clé
            primaire. Souvent, c'est une colonne `ID`.
            -   `NOT NULL` : Assure qu'une colonne ne peut pas avoir de valeur vide (`NULL`).
            -   `UNIQUE` : Garantit que toutes les valeurs dans une colonne sont différentes (comme pour un email ou un nom
            d'utilisateur).
            
            ### 🌟 Exemple avec Contraintes
            
            Ajoutons des contraintes à notre table `utilisateurs` :
            
            ```sql
            CREATE TABLE utilisateurs (
                utilisateur_id INT PRIMARY KEY,
                nom_utilisateur VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                date_creation DATE DEFAULT CURRENT_DATE
            );
            ```
            
            Ce qui est nouveau ici :
            -   `PRIMARY KEY` sur `utilisateur_id` : Chaque `utilisateur_id` doit être unique et non nul.
            -   `NOT NULL UNIQUE` sur `nom_utilisateur` et `email` : Ces champs ne peuvent pas être vides et doivent être uniques
            pour chaque utilisateur.
            -   `DEFAULT CURRENT_DATE` sur `date_creation` : Si aucune date n'est spécifiée lors de l'insertion d'un nouvel
            utilisateur, la date actuelle sera automatiquement utilisée.
            
            > ⚠️ **CONSEIL IMPORTANT :** Il est souvent recommandé de laisser la base de données générer automatiquement les
            `PRIMARY KEY` (par exemple, en utilisant `AUTO_INCREMENT` ou `SERIAL`, selon votre système de base de données). Cela
            évite les erreurs de duplication.
            
            ## 📚 Prochaines Étapes
            
            Maintenant que vous savez comment créer une table, vous pouvez commencer à insérer des données dedans ! N'hésitez pas
            si vous avez d'autres questions.
        MARKDOWN;

        return str_split($response, 100);
    }
}