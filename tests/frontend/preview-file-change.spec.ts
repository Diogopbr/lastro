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

describe('Preview de importação - troca de arquivo e conflito 409', () => {
  const dummyConference: ConferenceProjection = {
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
    normalizedHash: 'hash-abc',
    rowCount: 1,
    grossTotal: '100.00',
    rows: [
      { line: 2, orderId: 'ORD-1', payoutId: null, businessDate: '2026-10-02', gross: '100.00', fee: null, net: null },
    ],
    errors: [],
  };

  it('limpa previa anterior ao trocar ou remover o arquivo', async () => {
    const wrapper = mount(Preview, {
      props: {
        conference: dummyConference,
        kind: 'orders',
        preview: dummyPreview,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    expect(wrapper.text()).toContain('Resultado da prévia');

    // Simulate changing/clearing file in FilePicker
    const filePicker = wrapper.findComponent({ name: 'FilePicker' });
    expect(filePicker.exists()).toBe(true);

    // Emit change with a new file or null
    filePicker.vm.$emit('change', null);
    await wrapper.vm.$nextTick();

    // Previous preview must be cleared
    expect(wrapper.text()).not.toContain('Resultado da prévia');
  });

  it('em conflito 409 preserva arquivo, oferece atualizar versao da conferencia e recalcula previa', async () => {
    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch');

    const wrapper = mount(Preview, {
      props: {
        conference: dummyConference,
        kind: 'orders',
        preview: dummyPreview,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // Provide a selected file
    const fakeFile = new File(['order_id,gross\n1,10.00'], 'pedidos.csv', { type: 'text/csv' });
    const filePicker = wrapper.findComponent({ name: 'FilePicker' });
    filePicker.vm.$emit('update:modelValue', fakeFile);
    filePicker.vm.$emit('change', fakeFile);

    // Simulate 409 conflict on confirm
    apiFetchSpy.mockRejectedValueOnce({
      status: 409,
      code: 'conflict',
      message: 'Conflito de versão: a conferência foi alterada por outro usuário.',
    });

    // We can manually re-assign preview to test confirm
    (wrapper.vm as unknown as { currentPreview: ImportPreview }).currentPreview = dummyPreview;
    await wrapper.vm.$nextTick();

    const confirmBtn = wrapper.find('[data-test="btn-confirm-import"]');
    await confirmBtn.trigger('click');
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 20));

    // File must be preserved
    expect((wrapper.vm as unknown as { selectedFile: File | null }).selectedFile).toBe(fakeFile);

    // Button to update conference version should be visible
    const refreshBtn = wrapper.find('[data-test="btn-refresh-conference-version"]');
    expect(refreshBtn.exists()).toBe(true);

    // Setup spies for refresh
    const updatedConference: ConferenceProjection = {
      ...dummyConference,
      version: 2,
    };
    apiFetchSpy.mockResolvedValueOnce(updatedConference); // for GET /conferencias/conf-1
    apiFetchSpy.mockResolvedValueOnce(dummyPreview); // for POST preview

    await refreshBtn.trigger('click');
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 20));

    // Check that GET /conferencias/conf-1 was called
    expect(apiFetchSpy).toHaveBeenCalledWith('/conferencias/conf-1');

    // And version was updated to 2
    expect((wrapper.vm as unknown as { currentConference: ConferenceProjection }).currentConference.version).toBe(2);

    apiFetchSpy.mockRestore();
  });
});
