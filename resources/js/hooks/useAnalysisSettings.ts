import { useEffect, useRef, useState } from 'react';
import { HSSelect } from 'preline';
import { api } from '../lib/api';

export type ObjectCategory = {
    key: string;
    label: string;
    icon: string;
    objects: { value: string; label: string }[];
};

export type ObjectDetectionMode = 'all' | 'specific';

type AnalysisSettingsData = {
    analysis_types: string[];
    auto_start_analysis: boolean;
    analysis_config: { object_detection?: { mode: ObjectDetectionMode; objects?: string[] } } | null;
};

// Videos created before this feature existed have NULL in these columns —
// the API/model casts pass that through as null rather than an empty array.
type HydratableAnalysisSettingsData = {
    analysis_types: string[] | null;
    auto_start_analysis: boolean | null;
    analysis_config: AnalysisSettingsData['analysis_config'];
};

export function useAnalysisSettings() {
    const [analysisTypes, setAnalysisTypes] = useState<string[]>([]);
    const [autoStartAnalysis, setAutoStartAnalysis] = useState(false);
    const [objectCategories, setObjectCategories] = useState<ObjectCategory[]>([]);
    const [objectMode, setObjectMode] = useState<ObjectDetectionMode>('all');
    const [selectedObjects, setSelectedObjects] = useState<string[]>([]);
    const analysisTypesRef = useRef<HTMLSelectElement>(null);

    useEffect(() => {
        api.get<{ data: ObjectCategory[] }>('/api/object-detection-categories')
            .then((res) => setObjectCategories(res.data.data))
            .catch(() => setObjectCategories([]));
    }, []);

    useEffect(() => {
        if (!analysisTypes.includes('object_detection')) {
            setObjectMode('all');
            setSelectedObjects([]);
        }
    }, [analysisTypes]);

    function toggleObject(value: string) {
        setSelectedObjects((current) => (current.includes(value) ? current.filter((v) => v !== value) : [...current, value]));
    }

    // Only sets React state. If the <select> isn't mounted yet (e.g. still behind
    // a loading screen), call syncSelectVisual() once it appears to sync the
    // uncontrolled Preline widget — it won't pick up React state on its own.
    function hydrate(data: HydratableAnalysisSettingsData) {
        // Only one type can be selected now; older data saved with several
        // (or a legacy video saved with none) is truncated to just the first.
        setAnalysisTypes((data.analysis_types ?? []).slice(0, 1));
        setAutoStartAnalysis(data.auto_start_analysis ?? false);
        setObjectMode(data.analysis_config?.object_detection?.mode ?? 'all');
        setSelectedObjects(data.analysis_config?.object_detection?.objects ?? []);
    }

    function syncSelectVisual() {
        if (analysisTypesRef.current) {
            HSSelect.getInstance(analysisTypesRef.current)?.setValue(analysisTypes[0] ?? '');
        }
    }

    function reset() {
        setAnalysisTypes([]);
        setAutoStartAnalysis(false);
        setObjectMode('all');
        setSelectedObjects([]);
        if (analysisTypesRef.current) {
            HSSelect.getInstance(analysisTypesRef.current)?.setValue('');
        }
    }

    function appendToFormData(formData: FormData) {
        analysisTypes.forEach((type) => formData.append('analysis_types[]', type));
        formData.append('auto_start_analysis', autoStartAnalysis ? '1' : '0');
        if (analysisTypes.includes('object_detection')) {
            formData.append('analysis_config[object_detection][mode]', objectMode);
            if (objectMode === 'specific') {
                selectedObjects.forEach((value) => formData.append('analysis_config[object_detection][objects][]', value));
            }
        }
    }

    function toPayload(): AnalysisSettingsData {
        return {
            analysis_types: analysisTypes,
            auto_start_analysis: autoStartAnalysis,
            analysis_config: analysisTypes.includes('object_detection')
                ? { object_detection: { mode: objectMode, ...(objectMode === 'specific' ? { objects: selectedObjects } : {}) } }
                : null,
        };
    }

    return {
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
        hydrate,
        syncSelectVisual,
        reset,
        appendToFormData,
        toPayload,
    };
}
