import { afterEach, describe, expect, it, vi } from 'vitest';
import { apiFetch } from '../../resources/js/core/api';

afterEach(() => {
  document.querySelector('meta[name="lastro-workspace"]')?.remove();
  vi.unstubAllGlobals();
});

describe('API generation when another tab resets the shared session', () => {
  it('keeps the rendered generation even when fetching a fresh CSRF token changes the session', async () => {
    const meta = document.createElement('meta');
    meta.name = 'lastro-workspace';
    meta.content = 'old-generation';
    document.head.append(meta);
    const fetchMock = vi.fn().mockImplementationOnce(async () => {
      meta.content = 'new-generation';
      return Response.json({ token: 'fresh-token' });
    }).mockResolvedValueOnce(Response.json({ id: 'created' }));
    vi.stubGlobal('fetch', fetchMock);
    await apiFetch('/conferencias', { method: 'POST', data: { name: 'Draft from old page' } });
    const headers = fetchMock.mock.calls[1][1].headers as Headers;
    expect(headers.get('X-Lastro-Workspace')).toBe('old-generation');
    expect(headers.get('X-CSRF-TOKEN')).toBe('fresh-token');
  });

  it('does not invent a workspace header on the guest entry', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce(Response.json({ token: 'fresh-token' })).mockResolvedValueOnce(Response.json({ redirect: '/conferencias' }));
    vi.stubGlobal('fetch', fetchMock);
    await apiFetch('/demo/start', { method: 'POST' });
    expect((fetchMock.mock.calls[1][1].headers as Headers).has('X-Lastro-Workspace')).toBe(false);
  });
});
