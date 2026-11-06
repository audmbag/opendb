import {
    CodeBlock,
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';

/**
 * The output of the engine, unchanged, highlighted by the code block of the conversation.
 */
export default function PlanJson({ raw }: { raw: unknown }) {
    return (
        <div className="border-t px-4 py-4">
            <CodeBlock
                code={JSON.stringify(raw, null, 2) ?? 'null'}
                language="json"
                showLineNumbers
                className="[&_pre]:max-h-120 [&_pre]:overflow-auto [&_pre]:text-xs [&_pre_code]:text-xs"
            >
                <CodeBlockHeader>
                    <CodeBlockTitle>Sortie brute du moteur</CodeBlockTitle>
                    <CodeBlockCopyButton
                        className="size-7"
                        aria-label="Copier le JSON"
                        title="Copier le JSON"
                    />
                </CodeBlockHeader>
            </CodeBlock>
        </div>
    );
}
