import { cn } from '@/lib/utils';

export function H1(props: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h1
            {...props}
            className={cn(
                'scroll-m-20 text-center text-4xl font-extrabold tracking-tight text-balance',
                props.className,
            )}
        >
            {props.children}
        </h1>
    );
}

export function H2(props: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h2
            {...props}
            className={cn(
                'scroll-m-20 text-3xl font-semibold tracking-tight first:mt-0',
                props.className,
            )}
        >
            {props.children}
        </h2>
    );
}

export function H3(props: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3
            {...props}
            className={cn(
                'scroll-m-20 text-2xl font-semibold tracking-tight',
                props.className,
            )}
        >
            {props.children}
        </h3>
    );
}

export function H4(props: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h4
            {...props}
            className={cn(
                'scroll-m-20 text-xl font-semibold tracking-tight',
                props.className,
            )}
        >
            {props.children}
        </h4>
    );
}

export function H5(props: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h5
            {...props}
            className={cn(
                'scroll-m-20 text-base font-semibold tracking-tight',
                props.className,
            )}
        >
            {props.children}
        </h5>
    );
}

export function H6(props: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h6
            {...props}
            className={cn(
                'scroll-m-20 text-sm font-semibold tracking-tight',
                props.className,
            )}
        >
            {props.children}
        </h6>
    );
}


export function P(props: React.HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p
            {...props}
            className={cn('leading-7 not-first:mt-6', props.className)}
        >
            {props.children}
        </p>
    );
}

export function Lead(props: React.HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p
            {...props}
            className={cn('text-xl text-muted-foreground', props.className)}
        >
            {props.children}
        </p>
    );
}

export function Large(props: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            {...props}
            className={cn('text-lg font-semibold', props.className)}
        >
            {props.children}
        </div>
    );
}

export function Small(props: React.HTMLAttributes<HTMLParagraphElement>) {
    return (
        <small
            {...props}
            className={cn('text-sm leading-none font-medium', props.className)}
        >
            {props.children}
        </small>
    );
}

export function Muted(props: React.HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p
            {...props}
            className={cn('text-sm text-muted-foreground', props.className)}
        >
            {props.children}
        </p>
    );
}
