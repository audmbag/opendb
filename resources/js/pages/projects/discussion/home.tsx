import { router, usePage } from '@inertiajs/react';
import PromptInput from '@/components/ai/prompt-input';
import AppLogo from '@/components/app-logo';
import { H3 } from '@/components/typograpfy/typography';
import AppLayout from '@/layouts/app';
import ChatLayout from '@/layouts/chat-layout';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';

const Home = () => {

    const page = usePage<{project: Project}>();



    return (
        <div className="flex h-full flex-col items-center gap-8 p-4 pt-32">
            <AppLogo />
            <H3 className="font-normal">
                Comment puis-je vous aider aujourd'hui ?
            </H3>
            <PromptInput
                onSubmit={(message) => {
                    console.log(message);
                    // router.visit(
                    //     ConversationController.discussion({
                    //         project: page.props.project.id as string,
                    //         discussion: 'salut_les_gens_bien',
                    //     }),
                    //     {
                    //         viewTransition: true,
                    //     },
                    // )
                }}
                className="[view-transition-name:prompt-input]"
            />
        </div>
    );
}

Home.layout = [AppLayout, ChatLayout];

export default Home;



