'use client';

import { useCallback, useEffect, useState } from 'react';
import api, { errorMessage } from './api';
import type { Lookups } from './types';

interface Result<T> {
    key: string;
    data: T | null;
    error: string | null;
}

/**
 * Fetches JSON from the API whenever the URL or params change. Pass a null URL to skip.
 * Previous data stays visible while a new request is loading.
 */
export function useApi<T>(url: string | null, params?: Record<string, unknown>) {
    const [version, setVersion] = useState(0);
    const [result, setResult] = useState<Result<T> | null>(null);
    const paramsKey = JSON.stringify(params ?? {});
    const key = url ? `${url}|${paramsKey}|${version}` : null;

    useEffect(() => {
        if (!url || !key) return;
        let cancelled = false;
        api.get<T>(url, { params: JSON.parse(paramsKey) })
            .then((res) => !cancelled && setResult({ key, data: res.data, error: null }))
            .catch((err) => !cancelled && setResult((prev) => ({ key, data: prev?.data ?? null, error: errorMessage(err) })));
        return () => {
            cancelled = true;
        };
    }, [url, paramsKey, key]);

    const reload = useCallback(() => setVersion((v) => v + 1), []);

    return {
        data: result?.data ?? null,
        error: result?.key === key ? result.error : null,
        loading: key !== null && result?.key !== key,
        reload,
    };
}

let lookupsCache: Promise<Lookups> | null = null;

export function useLookups(): Lookups | null {
    const [lookups, setLookups] = useState<Lookups | null>(null);

    useEffect(() => {
        lookupsCache ??= api.get<Lookups>('/lookups').then((res) => res.data);
        lookupsCache.then(setLookups).catch(() => {
            lookupsCache = null;
        });
    }, []);

    return lookups;
}
