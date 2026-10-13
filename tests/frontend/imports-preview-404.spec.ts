import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Preview from '../../resources/js/pages/Imports/Preview.vue';
import type { ConferenceProjection, ImportPreview } from '../../resources/js/core/contracts';
import * as apiModule from '../../resources/js/core/api';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
  return {
    ...actual,
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', render: () => null },
    usePage: () => ({
      props: {
        auth: { demoEnabled: false, user: null, workspace: null },
        flash: { success: null },
      },
      url: '/conferencias/conf-1/fontes/orders/preview',
    }),
  };
});

describe('Imports/Preview - recuperacao 404 e textos amigaveis', () => {
  const dummyConf: ConferenceProjection = {
    id: 'conf-1',
    code: 'LT-01',
    name: 'Conferência Outubro',
    startDate: '2026-10-01',
    endDate: '2026-10-10',
    version: 1,
    createdAt: '2026-10-10T12:00:00Z',
    updatedAt: '2026-10-10T12:00:00Z',
    sources: { orders: null, payouts: null },
    run: null,
    progress: null,
  };

  const dummyPreview: ImportPreview = {
    valid: true,
    normalizedHash: 'hash-xyz',
    rowCount: 2,
    grossTotal: '250.00',
    rows: [
      { line: 2, orderId: 'ORD-1', payoutId: null, businessDate: '2026-10-05', gross: '150.00', fee: null, net: null },
      { line: 3, orderId: 'ORD-2', payoutId: null, businessDate: '2026-10-06', gross: '100.00', fee: null, net: null },
    ],
    errors: [],
  };

  it('exibe textos voltados ao usuario e botao Confirmar importacao', () => {
    const wrapper = mount(Preview, {
      props: {
        conference: dummyConf,
        kind: 'orders',
        preview: dummyPreview,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    expect(wrapper.text()).toContain('Confira as linhas e valores');
    const confirmBtn = wrapper.find('[data-test="btn-confirm-import"]');
    expect(confirmBtn.text()).toBe('Confirmar importação');
  });

  it('em erro 404 durante confirmacao explica expiracao de demo e oferece iniciar nova demo sem reenvio automatico', async () => {
    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockRejectedValueOnce({
      status: 404,
      code: 'not_found',
      message: 'Conferência não encontrada ou expirada.',
    });

    const wrapper = mount(Preview, {
      props: {
        conference: dummyConf,
        kind: 'orders',
        preview: dummyPreview,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // Provide a file to allow confirmation
    const fakeFile = new File(['csv'], 'pedidos.csv', { type: 'text/csv' });
    (wrapper.vm as unknown as { selectedFile: File | null }).selectedFile = fakeFile;
    await wrapper.vm.$nextTick();

    const confirmBtn = wrapper.find('[data-test="btn-confirm-import"]');
    await confirmBtn.trigger('click');
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 20));

    // Must show 404 explanation about expired demo / reset
    expect(wrapper.text()).toContain('expirou');

    // Must offer button to start new demo
    const newDemoBtn = wrapper.find('[data-test="btn-start-new-demo-after-404"]');
    expect(newDemoBtn.exists()).toBe(true);

    apiFetchSpy.mockRestore();
  });
});
