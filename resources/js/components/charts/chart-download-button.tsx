import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ImageDownIcon } from 'lucide-react';
import type { RefObject } from 'react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import Button from '@/components/form/button';
import { chartImage } from '@/lib/chart-image';
import { fileNameOf, saveBlob, waitForPaint } from '@/lib/save-file';

type Props = {
    /**
     * The element holding the chart, its legend included.
     */
    chartRef: RefObject<HTMLElement | null>;
    /**
     * The title of the chart, giving its name to the file.
     */
    title: string;
    disabled?: boolean;
    className?: string;
};

/**
 * Saves the chart as a PNG, as it shows on the page.
 */
export default function ChartDownloadButton({
    chartRef,
    title,
    disabled,
    className,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [processing, setProcessing] = useState(false);

    const download = useCallback(async () => {
        if (!chartRef.current) {
            return;
        }

        setProcessing(true);

        try {
            await waitForPaint();

            const image =
                chartRef.current && (await chartImage(chartRef.current));

            if (!image) {
                throw new Error('The chart could not be drawn.');
            }

            saveBlob(image, `${fileNameOf(title, 'chart')}.png`);
        } catch (error) {
            console.error(error);
            toast.error(t('charts.download_failed'));
        } finally {
            setProcessing(false);
        }
    }, [chartRef, title, t]);

    return (
        <Button
            icon={ImageDownIcon}
            size="icon-sm"
            variant="ghost"
            tooltip={t('charts.download')}
            aria-label={t('charts.download')}
            onClick={download}
            processing={processing}
            disabled={disabled}
            className={className}
        />
    );
}
