import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import PromptInput from '@/components/ai/prompt-input';
import AppLogo from '@/components/app-logo';
import { H3 } from '@/components/typograpfy/typography';
import { useConversation } from '@/hooks/use-conversation';
import AppLayout from '@/layouts/app';
import ChatLayout from '@/layouts/chat-layout';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';

const Home = () => {

    const { startConversationRequest, startConversation } = useConversation();
    const agentSettings = usePage().props.auth.user.settings.agent;

    // A new conversation starts with the mode and the effort chosen in the settings, whatever the last conversation used.
    useEffect(() => {
        const { setAccessMode, setEffort } = useAgentSettingsStore.getState();

        setAccessMode(agentSettings.accessMode);
        setEffort(agentSettings.effort);
    }, [agentSettings.accessMode, agentSettings.effort]);

    return (
        <div className="flex h-full flex-col items-center gap-8 p-4 pt-32">
            <AppLogo />
            <H3 className="font-normal">
                Comment puis-je vous aider aujourd'hui ?
            </H3>
            <PromptInput
                onSubmit={startConversation}
                className="[view-transition-name:prompt-input]"
                loading={startConversationRequest.processing}
            />
        </div>
    );
}

Home.layout = [AppLayout, ChatLayout];

export default Home;



