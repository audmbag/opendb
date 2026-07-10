import { AlertTriangleIcon, Code2Icon, DatabaseIcon, FilePlusIcon, NetworkIcon, ZapIcon } from "lucide-react";
import Button from "@/components/form/button";


const Proposals = [
    {
        id: 'db-summary',
        prompt: 'Fais-moi un résumé de la structure de cette BDD.',
        icon: DatabaseIcon,
    },
    {
        id: 'db-schema',
        prompt: 'Génère le schéma de la base de données au format Mermaid.',
        icon: NetworkIcon,
    },
    {
        id: 'suggest-indexes',
        prompt: 'Analyse mes tables et suggère des index pour optimiser les performances.',
        icon: ZapIcon,
    },
    {
        id: 'check-anomalies',
        prompt: 'Y a-t-il des valeurs nulles ou des anomalies dans mes tables principales ?',
        icon: AlertTriangleIcon,
    },
    {
        id: 'gen-seeder',
        prompt: 'Crée un Seeder Laravel réaliste pour ma table principale.',
        icon: Code2Icon,
    },
    {
        id: 'new-migration',
        prompt: "Génère une migration pour ajouter une table de logs d'activité.",
        icon: FilePlusIcon,
    },
];

export default function ChatQuestionProposals() {
    return <div className="flex flex-wrap gap-1">
        {Proposals.map((proposal) => (
            <Button
                key={proposal.id}
                variant="outline"
                className="flex-1 gap-2"
                size="sm"
                icon={proposal.icon}
            >
                {proposal.prompt}
            </Button>
        ))}
    </div>
}