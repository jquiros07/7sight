export type VideoStatus = 'uploaded' | 'processing' | 'ready' | 'failed';

export const VIDEO_STATUS_STYLES: Record<VideoStatus, { label: string; className: string }> = {
    uploaded: { label: 'Uploaded', className: 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-400' },
    processing: { label: 'Processing', className: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400' },
    ready: { label: 'Analyzed', className: 'bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400' },
    failed: { label: 'Failed', className: 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-400' },
};
