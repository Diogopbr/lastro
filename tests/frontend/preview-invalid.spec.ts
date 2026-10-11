import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Preview from '../../resources/js/pages/Imports/Preview.vue';
import type { ConferenceProjection, ImportPreview } from '../../resources/js/core/contracts';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
  return {
    ...actual,
    Head: {
      name: 'Head',
      render: () => null,
    },
  };
});

describe('Preview de importação', () => {
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

  it('preview inválido impede confirm', async () => {
    const invalidPreview: ImportPreview = {
      valid: false,
      normalizedHash: null,
      rowCount: 2,
      grossTotal: '150.00',
      rows: [
        { line: 2, orderId: 'ORD-1', payoutId: null, businessDate: '2026-10-05', gross: '150.00', fee: null, net: null },
      ],
      errors: [
        { line: 3, field: 'gross_amount', code: 'invalid_currency', message: 'Valor bruto inválido na linha 3' },
      ],
    };

    const wrapper = mount(Preview, {
      props: {
        conference: dummyConference,
        kind: 'orders',
        preview: invalidPreview,
      },
      global: {
        stubs: {
          Head: true,
          Link: true,
        },
      },
    });

    // The confirm button should be present in the UI but disabled
    const confirmButton = wrapper.find('[data-test="btn-confirm-import"]');
    expect(confirmButton.exists()).toBe(true);
    expect(confirmButton.attributes('disabled')).toBeDefined();

    // Check that error message is rendered
    expect(wrapper.text()).toContain('Valor bruto inválido na linha 3');
  });
});
