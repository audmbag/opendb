import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon } from 'lucide-react';
import { CheckIcon, DatabaseIcon, TriangleAlertIcon, XIcon } from 'lucide-react';
import { AnimatePresence, MotionConfig, motion, useReducedMotion } from 'motion/react';
import type { Transition } from 'motion/react';
import React from 'react';
import IconStackGlow from '@/components/icon-stack-glow';
import { Muted } from '@/components/typograpfy/typography';
import { trans } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type TransferStatus = 'transferring' | 'success' | 'warning' | 'error';

type Endpoint = {
    driverId?: string;
    database?: string | null;
};

type Props = {
    source: Endpoint;
    destination: Endpoint;
    status?: TransferStatus;
} & React.ComponentProps<'div'>;

const PACKETS = 5;
const DURATION = 2;
const INTERVAL = DURATION / PACKETS;
const BREATHING_DURATION = 3.2;

/**
 * Demi-sinusoïdes : enchaînées, elles donnent une oscillation sans à-coup entre 0.9 et 1.1.
 */
const SINE_OUT = [0.61, 1, 0.88, 1] as const;
const SINE_IN = [0.12, 0, 0.39, 0] as const;

const SWAP_TRANSITION: Transition = { type: 'spring', bounce: 0.35, duration: 0.6 };

/**
 * Couleur de chaque état : le transfert garde la couleur par défaut (primary).
 */
const STATUS_COLORS: Record<TransferStatus, string> = {
    transferring: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    error: 'text-destructive',
};

const STATUSES: Record<Exclude<TransferStatus, 'transferring'>, { icon: LucideIcon; label: string }> = {
    success: { icon: CheckIcon, label: 'cloning.ui.animation.success' },
    warning: { icon: TriangleAlertIcon, label: 'cloning.ui.status.warning.title' },
    error: { icon: XIcon, label: 'cloning.ui.status.error.title' },
};

/**
 * Nom affiché d'une base : le nom du fichier pour SQLite, le nom de la base sinon.
 */
const databaseName = (database?: string | null) => database?.split(/[\\/]/).pop() || trans('projects.status.database');

const DriverTile = ({ driverId }: Pick<Endpoint, 'driverId'>) => {
    return (
        <IconStackGlow
            size="xl"
            icon={driverId ? <img src={`/assets/images/${driverId}.png`} alt="" className="size-7" /> : DatabaseIcon}
        />
    );
};

const EndpointLabel = ({ database }: Pick<Endpoint, 'database'>) => {
    useLaravelReactI18n();

    return (
        <Muted className="w-full truncate text-center text-xs" title={database ?? undefined}>
            {databaseName(database)}
        </Muted>
    );
};

/**
 * Bloc de réception : il respire pendant le transfert, puis se transforme en icône d'état à la fin.
 */
const DestinationEndpoint = ({ driverId, database, status }: Endpoint & { status: TransferStatus }) => {
    const animated = !useReducedMotion();
    const transferring = status === 'transferring';
    const result = transferring ? null : STATUSES[status];

    return (
        <div className="flex w-24 shrink-0 flex-col items-center gap-2">
            <motion.div
                className="relative"
                initial={{ scale: 1 }}
                animate={transferring && animated ? { scale: [1, 1.1, 1, 0.9, 1] } : { scale: 1 }}
                transition={
                    transferring && animated
                        ? { duration: BREATHING_DURATION, repeat: Infinity, ease: [SINE_OUT, SINE_IN, SINE_OUT, SINE_IN] }
                        : SWAP_TRANSITION
                }
            >
                <AnimatePresence mode="wait" initial={false}>
                    {result ? (
                        <motion.div
                            key={status}
                            className={cn('relative', STATUS_COLORS[status])}
                            initial={{ scale: 0.4, rotate: -90, opacity: 0, filter: 'blur(4px)' }}
                            animate={{ scale: 1, rotate: 0, opacity: 1, filter: 'blur(0px)' }}
                            exit={{ scale: 0.4, opacity: 0, filter: 'blur(4px)' }}
                            transition={SWAP_TRANSITION}
                        >
                            <motion.span
                                aria-hidden
                                className="absolute inset-0 rounded-xl border-2 border-current"
                                initial={{ scale: 1, opacity: 0.6 }}
                                animate={{ scale: 1.6, opacity: 0 }}
                                transition={{ duration: 0.9, ease: 'easeOut', delay: 0.15 }}
                            />
                            <IconStackGlow size="xl" variant="soft" className={STATUS_COLORS[status]} icon={result.icon} />
                        </motion.div>
                    ) : (
                        <motion.div
                            key="driver"
                            initial={{ scale: 0.4, opacity: 0, filter: 'blur(4px)' }}
                            animate={{ scale: 1, opacity: 1, filter: 'blur(0px)' }}
                            exit={{ scale: 0.4, rotate: 90, opacity: 0, filter: 'blur(4px)' }}
                            transition={{ duration: 0.25, ease: 'easeIn' }}
                        >
                            <DriverTile driverId={driverId} />
                        </motion.div>
                    )}
                </AnimatePresence>
            </motion.div>
            <EndpointLabel database={database} />
        </div>
    );
};

/**
 * Deux blocs, la source et la destination, entre lesquels des paquets de données circulent pendant le transfert.
 * À la fin, les paquets disparaissent et la destination prend l'icône de `status`.
 * Les paquets restent immobiles lorsque l'utilisateur a demandé à réduire les animations.
 */
export default function DataTransferAnimation({ source, destination, status = 'transferring', className, ...props }: Props) {
    const { t } = useLaravelReactI18n();
    const animated = !useReducedMotion();
    const transferring = status === 'transferring';
    const sourceName = databaseName(source.database);
    const destinationName = databaseName(destination.database);

    return (
        <MotionConfig reducedMotion="user">
            <div
                role="img"
                aria-label={transferring ? t('cloning.ui.animation.transferring', { source: sourceName, destination: destinationName }) : t(STATUSES[status].label)}
                className={cn('flex w-full items-start gap-3', className)}
                {...props}
            >
                <div className="flex w-24 shrink-0 flex-col items-center gap-2">
                    <DriverTile driverId={source.driverId} />
                    <EndpointLabel database={source.database} />
                </div>

                <div aria-hidden className="relative mt-5 h-4 flex-1 overflow-hidden">
                    <div
                        className={cn(
                            'absolute inset-x-0 top-1/2 -translate-y-1/2 border-t-2 border-dashed border-current/25 transition-colors duration-700',
                            STATUS_COLORS[status],
                        )}
                    />

                    <AnimatePresence>
                        {transferring && (
                            <motion.div
                                key="packets"
                                className="absolute inset-0"
                                exit={{ opacity: 0 }}
                                transition={{ duration: 0.4 }}
                            >
                                {Array.from({ length: PACKETS }, (_, index) => (
                                    <motion.div
                                        key={index}
                                        className="absolute inset-y-0 right-2 left-0"
                                        initial={{ x: animated ? '0%' : `${(index + 0.5) * (100 / PACKETS)}%`, opacity: animated ? 0 : 1 }}
                                        animate={animated ? { x: ['0%', '100%'], opacity: [0, 1, 1, 0] } : undefined}
                                        transition={{
                                            duration: DURATION,
                                            repeat: Infinity,
                                            ease: 'linear',
                                            delay: index * INTERVAL,
                                            opacity: { duration: DURATION, repeat: Infinity, ease: 'linear', delay: index * INTERVAL, times: [0, 0.15, 0.85, 1] },
                                        }}
                                    >
                                        <span
                                            className={cn(
                                                'absolute top-1/2 left-0 -translate-y-1/2 rounded-[2px] bg-primary',
                                                index % 2 === 0 ? 'size-2' : 'size-1.5 bg-primary/60',
                                            )}
                                        />
                                    </motion.div>
                                ))}
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>

                <DestinationEndpoint {...destination} status={status} />
            </div>
        </MotionConfig>
    );
}
