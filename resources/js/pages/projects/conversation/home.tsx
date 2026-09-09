import PromptInput from '@/components/ai/prompt-input';
import AppLogo from '@/components/app-logo';
import { H3 } from '@/components/typograpfy/typography';
import { useConversation } from '@/hooks/use-conversation';
import AppLayout from '@/layouts/app';
import ChatLayout from '@/layouts/chat-layout';

const Home = () => {

    const { startConversationRequest, startConversation } = useConversation();

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



