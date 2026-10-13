import type { ApiError } from './contracts';

let inMemoryCsrfToken: string | null = null;

/**
 * Obtém token CSRF atualizado através de GET /csrf.
 * Nunca armazena tokens em localStorage.
 */
export async function fetchCsrfToken(forceFresh = false): Promise<string> {
  if (inMemoryCsrfToken && !forceFresh) {
    return inMemoryCsrfToken;
  }

  const response = await fetch('/csrf', {
    method: 'GET',
    headers: {
      Accept: 'application/json',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error('Falha ao obter token CSRF da sessão.');
  }

  const data = (await response.json()) as { token: string };
  inMemoryCsrfToken = data.token;
  return data.token;
}

export interface ApiFetchOptions extends RequestInit {
  data?: unknown;
}

/**
 * Cliente HTTP para a API Lastro com injeção automática de CSRF e headers JSON.
 */
export async function apiFetch<T>(url: string, options: ApiFetchOptions = {}): Promise<T> {
  const method = (options.method || 'GET').toUpperCase();
  const headers = new Headers(options.headers || {});
  headers.set('Accept', 'application/json');

  // Mutações requerem token CSRF fresco no cabeçalho X-CSRF-TOKEN
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    const expectedWorkspace = typeof document === 'undefined' ? '' : document.querySelector<HTMLMetaElement>('meta[name="lastro-workspace"]')?.content;
    if (expectedWorkspace) headers.set('X-Lastro-Workspace', expectedWorkspace);
    const csrfToken = await fetchCsrfToken(true);
    headers.set('X-CSRF-TOKEN', csrfToken);
  }

  let body = options.body;
  if (options.data !== undefined) {
    if (options.data instanceof FormData) {
      body = options.data;
    } else {
      headers.set('Content-Type', 'application/json');
      body = JSON.stringify(options.data);
    }
  }

  const response = await fetch(url, {
    ...options,
    method,
    headers,
    body,
    credentials: 'same-origin',
  });

  if (!response.ok) {
    let errorData: ApiError;
    try {
      errorData = (await response.json()) as ApiError;
    } catch {
      errorData = {
        code: `http_${response.status}`,
        message: response.statusText || 'Erro na requisição.',
      };
    }

    const error = new Error(errorData.message || 'Erro na requisição') as Error & {
      status: number;
      code?: string;
      errors?: Record<string, string[]>;
    };
    error.status = response.status;
    error.code = errorData.code;
    error.errors = errorData.errors;
    throw error;
  }

  // Respostas 204 No Content
  if (response.status === 204) {
    return {} as T;
  }

  return (await response.json()) as T;
}
