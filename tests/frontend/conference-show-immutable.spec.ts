import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Show from '../../resources/js/pages/Conferences/Show.vue';
import type { ConferenceProjection } from '../../resources/js/core/contracts';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
  return {
    ...actual,
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', template: '<a><slot /></a>' },
    usePage: () => ({
      props: {
        auth: { demoEnabled: false, user: null, workspace: null },
        flash: { success: null },
      },
      url: '/conferencias/conf-1',
    }),
  };
});

describe('Conferences/Show - Imutabilidade de fontes confirmadas', () => {
  it('exibe explicacao de imutabilidade e texto "Conferir arquivo novamente" em vez de substituição', () => {
    const confWithSource: ConferenceProjection = {
      id: 'conf-1',
      code: 'LT-01',
      name: 'Conferência Outubro',
      startDate: '2026-10-01',
      endDate: '2026-10-10',
      version: 2,
      createdAt: '2026-10-10T12:00:00Z',
      updatedAt: '2026-10-10T12:00:00Z',
      sources: {
        orders: {
          id: 'src-1',
          kind: 'orders',
          normalizedHash: 'hash-abc',
          displayFilename: 'pedidos.csv',
          rowCount: 10,
          grossTotal: '1500.00',
          confirmedAt: '2026-10-10T14:00:00Z',
        },
        payouts: null,
      },
      run: null,
      progress: null,
    };

    const wrapper = mount(Show, {
      props: {
        conference: confWithSource,
      },
      global: {
        stubs: {
          Head: true,
          Link: { template: '<a><slot /></a>' },
        },
      },
    });

    // Deve conter 'Conferir arquivo novamente'
    expect(wrapper.text()).toContain('Conferir arquivo novamente');

    // Nao deve conter 'Substituir arquivo'
    expect(wrapper.text()).not.toContain('Substituir arquivo');

    // Deve explicar imutabilidade
    expect(wrapper.text()).toContain('imutáveis');
  });
});
