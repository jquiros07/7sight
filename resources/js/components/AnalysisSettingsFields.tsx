import type { useAnalysisSettings } from '@/hooks/useAnalysisSettings';
import { Label } from '@/components/ui/label';

const ANALYSIS_TYPE_OPTIONS = [
    { value: 'content_moderation', label: 'Content Moderation', disabled: false },
    { value: 'object_detection', label: 'Object Detection', disabled: false },
    { value: 'threat_detection', label: 'Threat Detection', disabled: false },
    { value: 'ai_generated', label: 'AI Generated', disabled: true },
] as const;

const ANALYSIS_TYPE_SELECT_CONFIG = JSON.stringify({
    placeholder: 'Select analysis types...',
    toggleTag: '<button type="button" aria-expanded="false"></button>',
    toggleClasses:
        'hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 relative py-1.5 ps-3 pe-9 flex text-nowrap w-full cursor-pointer bg-layer border border-layer-line text-layer-foreground rounded-lg text-start text-sm hover:bg-layer-hover focus:outline-hidden focus:bg-layer-focus',
    dropdownClasses:
        'mt-2 z-50 w-full max-h-72 p-1 space-y-0.5 bg-select border border-select-line rounded-lg shadow-xl overflow-hidden overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-none [&::-webkit-scrollbar-track]:bg-scrollbar-track [&::-webkit-scrollbar-thumb]:bg-scrollbar-thumb',
    optionClasses:
        'hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 py-2 px-4 w-full text-sm text-select-item-foreground cursor-pointer hover:bg-select-item-hover rounded-lg focus:outline-hidden focus:bg-select-item-focus',
    optionTemplate:
        '<div class="flex justify-between items-center w-full"><span data-title></span><span class="hidden hs-selected:block"><svg class="shrink-0 size-3.5 text-primary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span></div>',
    extraMarkup:
        '<div class="absolute top-1/2 inset-e-3 -translate-y-1/2"><svg class="shrink-0 size-3.5 text-muted-foreground-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></svg></div>',
});

export function AnalysisSettingsFields({
    analysisTypes,
    setAnalysisTypes,
    autoStartAnalysis,
    setAutoStartAnalysis,
    objectCategories,
    objectMode,
    setObjectMode,
    selectedObjects,
    toggleObject,
    analysisTypesRef,
}: ReturnType<typeof useAnalysisSettings>) {
    return (
        <>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="video-analysis-types">Analysis type</Label>
                <select
                    ref={analysisTypesRef}
                    id="video-analysis-types"
                    multiple
                    data-hs-select={ANALYSIS_TYPE_SELECT_CONFIG}
                    onChange={(e) => setAnalysisTypes(Array.from(e.target.selectedOptions, (option) => option.value))}
                    className="hidden"
                >
                    {ANALYSIS_TYPE_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value} disabled={option.disabled}>
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            {analysisTypes.includes('object_detection') && (
                <div className="flex flex-col gap-3 rounded-lg border border-layer-line p-4">
                    <div className="flex flex-col gap-1.5">
                        <span className="text-sm font-medium text-foreground">Objects to detect</span>
                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input
                                type="radio"
                                name="object-detection-mode"
                                checked={objectMode === 'all'}
                                onChange={() => setObjectMode('all')}
                                className="size-4 border-layer-line text-primary focus:ring-primary-focus"
                            />
                            All objects
                        </label>
                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input
                                type="radio"
                                name="object-detection-mode"
                                checked={objectMode === 'specific'}
                                onChange={() => setObjectMode('specific')}
                                className="size-4 border-layer-line text-primary focus:ring-primary-focus"
                            />
                            Choose specific objects
                        </label>
                    </div>

                    {objectMode === 'specific' && (
                        <div className="max-h-72 overflow-y-auto rounded-lg border border-layer-line p-3">
                            {objectCategories.map((category) => (
                                <div key={category.key} className="mb-3 last:mb-0">
                                    <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground-1">
                                        {category.icon} {category.label}
                                    </p>
                                    <div className="grid grid-cols-2 gap-x-4 gap-y-1 sm:grid-cols-3">
                                        {category.objects.map((object) => (
                                            <label key={object.value} className="flex items-center gap-2 text-sm text-foreground">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedObjects.includes(object.value)}
                                                    onChange={() => toggleObject(object.value)}
                                                    className="size-4 rounded border-layer-line text-primary focus:ring-primary-focus"
                                                />
                                                {object.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <div className="flex items-center gap-2">
                <input
                    id="video-auto-start-analysis"
                    type="checkbox"
                    checked={autoStartAnalysis}
                    onChange={(e) => setAutoStartAnalysis(e.target.checked)}
                    className="size-4 rounded border-layer-line text-primary focus:ring-primary-focus"
                />
                <Label htmlFor="video-auto-start-analysis" className="cursor-pointer font-normal">
                    Start analysis automatically after upload
                </Label>
            </div>
        </>
    );
}
