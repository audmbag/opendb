import { Form } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { LogInIcon } from 'lucide-react';
import Alert from '@/components/alert';
import AppLogo from '@/components/app-logo';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import SocialButtons from '@/components/social-buttons';
import { H2, Lead } from '@/components/typograpfy/typography';
import { Separator } from '@/components/ui/separator';
import LoginController from '@/wayfinder/actions/App/Http/Controllers/Auth/LoginController';


const LoginForm = () => {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex min-h-svh">
            <div className="relative hidden flex-1 flex-col bg-muted p-8 lg:flex">
                <div className="flex-1">
                    <AppLogo
                        size="sm"
                    />
                </div>
                <Lead className="text-lg font-medium">
                    {t('login.tagline')}
                </Lead>
            </div>
            <Separator orientation="vertical" className="hidden lg:flex" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <Form
                    action={LoginController.processLogin()}
                    method="POST"
                    className="flex w-full m-auto max-w-lg flex-1 flex-col items-center justify-center gap-4 pt-16"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="flex flex-col gap-2 pb-4 w-full">
                                <AppLogo
                                    icon
                                    size="xs"
                                />
                                <H2 className="font-semibold -mb-1">
                                    {t('login.title')}
                                </H2>
                            </div>
                            {errors.failed && (
                                <Alert
                                    className="mb-4"
                                    variant="destructive"
                                    title={t('login.failed')}
                                    message={errors.failed}
                                />
                            )}
                            <Input
                                label={t('login.username')}
                                placeholder={t('login.username_placeholder')}
                                description={t('login.username_description')}
                                name="name"
                                error={errors.name}
                            />
                            <Input
                                label={t('login.password')}
                                placeholder={t('login.password_placeholder')}
                                description={t('login.password_description')}
                                name="password"
                                type="password"
                                error={errors.password}
                            />

                            <div className="flex w-full justify-end pt-4">
                                <Button
                                    icon={LogInIcon}
                                    processing={processing}
                                    type="submit"
                                    className="w-full flex-row-reverse"
                                >
                                    {t('login.submit')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
                <div className="p-4 flex justify-end w-full">
                    <SocialButtons />
                </div>
            </div>
        </div>
    );
}

export default LoginForm;
