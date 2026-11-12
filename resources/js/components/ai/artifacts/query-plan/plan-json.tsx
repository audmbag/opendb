import { useLaravelReactI18n } from 'laravel-react-i18n';
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
    const { t } = useLaravelReactI18n();

    return (
        <div className="border-t px-4 py-4">
            <CodeBlock
                code={JSON.stringify(raw, null, 2) ?? 'null'}
                language="json"
                showLineNumbers
                className="[&_pre]:max-h-120 [&_pre]:overflow-auto [&_pre]:text-xs [&_pre_code]:text-xs"
            >
                <CodeBlockHeader>
                    <CodeBlockTitle>{t('query_plan.raw_output')}</CodeBlockTitle>
                    <CodeBlockCopyButton
                        className="size-7"
                        aria-label={t('query_plan.copy_json')}
                        title={t('query_plan.copy_json')}
                    />
                </CodeBlockHeader>
            </CodeBlock>
        </div>
    );
}
