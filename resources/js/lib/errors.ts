import axios from 'axios';

export function getErrorMessages(error: unknown, fallback = 'Something went wrong. Please try again.'): string[] {
    if (axios.isAxiosError(error) && error.response?.status === 422) {
        const data = error.response.data as { errors?: Record<string, string[]>; message?: string };
        if (data.errors) {
            return Object.values(data.errors).flat();
        }
        if (data.message) {
            return [data.message];
        }
    }
    return [fallback];
}
