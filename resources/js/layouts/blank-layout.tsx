import AppLogo from '@/components/app-logo';
import SocialButtons from '@/components/social-buttons';


export default function BlankLayout({ children }: { children: React.ReactNode }) {
    return (
        <div className="flex h-screen w-full flex-col p-4">
            <main className="flex flex-1 flex-col">
                <div className="flex flex-1 flex-col items-center justify-center gap-4">
                    <AppLogo icon />
                    {children}
                </div>
            </main>
            <footer className="pt-4">
                <SocialButtons />
            </footer>
        </div>
    );
}
