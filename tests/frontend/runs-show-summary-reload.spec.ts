import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import RunsShow from '../../resources/js/pages/Runs/Show.vue';
import type { ConferenceProjection, RunProjection, PageOfResults, ResultProjection } from '../../resources/js/core/contracts';
import * as apiModule from '../../resources/js/core/api';

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
  return {
    ...actual,
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', template: '<a><slot /></a>' },
    router: { get: vi.fn() },
    usePage: () => ({
      props: {
        auth: { demoEnabled: false, user: null, workspace: null },
        flash: { success: null },
      },
      url: '/execucoes/run-1',
    }),
  };
});

describe('Runs/Show - Atualizacao de resumo por GET /execucoes/:id e recarga por GET /resultados/:id', () => {
  const mockConference: ConferenceProjection = {
    id: 'conf-1',
    code: 'LT-01',
    name: 'Conferência Teste',
    startDate: '2026-10-01',
    endDate: '2026-10-10',
    version: 1,
    createdAt: '2026-10-10T12:00:00Z',
    updatedAt: '2026-10-10T12:00:00Z',
    sources: { orders: null, payouts: null },
    run: null,
    progress: null,
  };

  const initialRun: RunProjection = {
    id: 'run-1',
    conferenceId: 'conf-1',
    createdAt: '2026-10-10T12:00:00Z',
    summary: {
      orderGross: '1000.00',
      payoutGross: '900.00',
      fees: '50.00',
      net: '850.00',
      divergences: 2,
      reviewed: 0,
      counts: { matched: 0, amountMismatch: 2, missingPayout: 0, missingOrder: 0 },
    },
  };

  const mockResults: PageOfResults = {
    page: 1,
    pageSize: 10,
    total: 2,
    items: [
      {
        id: 'res-1',
        orderId: 'ORD-001',
        classification: 'amountMismatch',
        reviewStatus: 'pending',
        difference: '-10.00',
        version: 1,
        order: null,
        payout: null,
        reviewEvents: [],
      },
      {
        id: 'res-2',
        orderId: 'ORD-002',
        classification: 'amountMismatch',
        reviewStatus: 'pending',
        difference: '-20.00',
        version: 1,
        order: null,
        payout: null,
        reviewEvents: [],
      },
    ],
  };

  it('reports failed summary and version reload instead of silently presenting stale data', async () => {
    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockRejectedValue(new Error('Network unavailable'));
    const wrapper = mount(RunsShow, { props: { conference: mockConference, run: initialRun, results: mockResults, filters: { page: 1, pageSize: 10 } } });
    const reviewPanel = wrapper.findComponent({ name: 'ReviewPanel' });
    reviewPanel.vm.$emit('saved', { ...mockResults.items[0], reviewStatus: 'reviewed', version: 2 });
    await flushPromises();
    expect(wrapper.text()).toContain('Não foi possível atualizar o resumo');
    reviewPanel.vm.$emit('reload-requested');
    await flushPromises();
    expect(wrapper.text()).toContain('Não foi possível carregar a versão atual');
    apiFetchSpy.mockRestore();
  });

  it('atualiza resumo pelo GET /execucoes/:id ao salvar revisao e reflete reviewed atualizado', async () => {
    const updatedRun: RunProjection = {
      ...initialRun,
      summary: {
        ...initialRun.summary,
        reviewed: 1,
      },
    };

    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockImplementation(async (url) => {
      if (url === '/execucoes/run-1') {
        return {
          conference: mockConference,
          run: updatedRun,
          results: mockResults,
          filters: {},
        };
      }
      return {};
    });

    const wrapper = mount(RunsShow, {
      props: {
        conference: mockConference,
        run: initialRun,
        results: mockResults,
        filters: { page: 1, pageSize: 10 },
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    expect(wrapper.text()).toContain('0 de 2 divergências revisadas');

    // Simula evento de revisão salva
    const updatedRes1: ResultProjection = {
      ...mockResults.items[0],
      reviewStatus: 'reviewed',
      version: 2,
    };

    const reviewPanel = wrapper.findComponent({ name: 'ReviewPanel' });
    reviewPanel.vm.$emit('saved', updatedRes1);
    await flushPromises();

    // Verificamos que chamou GET /execucoes/run-1
    expect(apiFetchSpy).toHaveBeenCalledWith('/execucoes/run-1');

    // E o texto do resumo foi atualizado para 1 de 2
    expect(wrapper.text()).toContain('1 de 2 divergências revisadas');

    apiFetchSpy.mockRestore();
  });

  it('recarrega resultado selecionado via GET /resultados/:id em reload-requested', async () => {
    const reloadedResult: ResultProjection = {
      ...mockResults.items[0],
      version: 2,
      reviewStatus: 'reviewed',
    };

    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockImplementation(async (url) => {
      if (url === '/resultados/res-1') {
        return reloadedResult;
      }
      return {};
    });

    const wrapper = mount(RunsShow, {
      props: {
        conference: mockConference,
        run: initialRun,
        results: mockResults,
        filters: { page: 1, pageSize: 10 },
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    const reviewPanel = wrapper.findComponent({ name: 'ReviewPanel' });
    reviewPanel.vm.$emit('reload-requested');
    await flushPromises();

    expect(apiFetchSpy).toHaveBeenCalledWith('/resultados/res-1');

    apiFetchSpy.mockRestore();
  });

  it('salvar e proxima nao altera selecao se o usuario já trocou de item enquanto request salvava', async () => {
    const wrapper = mount(RunsShow, {
      props: {
        conference: mockConference,
        run: initialRun,
        results: mockResults,
        filters: { page: 1, pageSize: 10 },
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // Item 1 está selecionado inicialmente
    expect((wrapper.vm as any).selectedResultId).toBe('res-1');

    // Usuário clica no item 2 antes do término do save do item 1
    (wrapper.vm as any).selectedResultId = 'res-2';

    // Salvar e próxima do item 1 emite save-and-next
    const updatedRes1: ResultProjection = {
      ...mockResults.items[0],
      reviewStatus: 'reviewed',
      version: 2,
    };
    (wrapper.vm as any).handleSaveAndNext(updatedRes1);
    await flushPromises();

    // Seleção deve permanecer em res-2, NÃO deve avançar para outra coisa
    expect((wrapper.vm as any).selectedResultId).toBe('res-2');
  });
});
