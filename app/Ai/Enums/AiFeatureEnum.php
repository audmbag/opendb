<?php

namespace App\Ai\Enums;

use App\Ai\Agents\SQLDatabaseAgent;
use App\Ai\Agents\Tables\QueryReviewerAgent;
use App\Ai\Agents\Tables\QueryWriterAgent;
use App\Ai\Agents\Tables\TableChatAgent;
use App\Ai\Agents\Tables\TableDesignAgent;
use App\Ai\Agents\Tables\TableFilterAgent;
use App\Ai\Agents\Tables\TableIndexAdvisorAgent;
use App\Ai\Agents\Tables\TableInsightsAgent;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Agent;

/**
 * The place of the application where a model was used, shown in the usage settings.
 */
enum AiFeatureEnum: string
{
    case Conversation = 'conversation';

    case TableChat = 'table_chat';

    case TableFilter = 'table_filter';

    case TableInsights = 'table_insights';

    case TableIndexes = 'table_indexes';

    case TableDesign = 'table_design';

    case QueryWriter = 'query_writer';

    case QueryReviewer = 'query_reviewer';

    case ConversationTitle = 'conversation_title';

    case Other = 'other';

    /**
     * The only anonymous agent of the application titles the new conversations.
     */
    public static function fromAgent(Agent $agent): self
    {
        return self::fromAgentClass($agent::class);
    }

    /**
     * @param  class-string<Agent>|string  $agentClass
     */
    public static function fromAgentClass(string $agentClass): self
    {
        return match ($agentClass) {
            SQLDatabaseAgent::class => self::Conversation,
            TableChatAgent::class => self::TableChat,
            TableFilterAgent::class => self::TableFilter,
            TableInsightsAgent::class => self::TableInsights,
            TableIndexAdvisorAgent::class => self::TableIndexes,
            TableDesignAgent::class => self::TableDesign,
            QueryWriterAgent::class => self::QueryWriter,
            QueryReviewerAgent::class => self::QueryReviewer,
            AnonymousAgent::class => self::ConversationTitle,
            default => self::Other,
        };
    }
}
