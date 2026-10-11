import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../../resources/js/pages/Conferences/Index.vue';
import Show from '../../resources/js/pages/Conferences/Show.vue';
import type { ConferenceProjection } from '../../resources/js/core/contracts';
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
      url: '/conferencias',
    }),
  };
});

describe('Conferencias - mutacoes com apiFetch e preservacao de estado', () => {
  const dummyConf: ConferenceProjection = {
    id: 'conf-10',
    code: 'LT-10',
    name: 'Conferência Novembro',
    startDate: '2026-11-01',
    endDate: '2026-11-15',
    version: 3,
    createdAt: '2026-11-01T12:00:00Z',
    updatedAt: '2026-11-01T12:00:00Z',
    sources: { orders: null, payouts: null },
    run: null,
    progress: null,
  };

  it('criacao em Index.vue utiliza apiFetch e preserva campos em 422 com erros visiveis', async () => {
    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockRejectedValueOnce({
      status: 422,
      code: 'validation_error',
      message: 'Dados de conferência inválidos.',
      errors: {
        name: ['O nome já está em uso.'],
        endDate: ['Data final deve ser posterior à data inicial.'],
      },
    });

    const wrapper = mount(Index, {
      props: {
        conferences: [],
        quota: { used: 0, limit: 5 },
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // Open creation box
    const openBtn = wrapper.find('[data-test="btn-new-conference"]');
    await openBtn.trigger('click');

    const nameInput = wrapper.find('input#conf-name');
    const startInput = wrapper.find('input#conf-start');
    const endInput = wrapper.find('input#conf-end');

    await nameInput.setValue('Conferência Duplicada');
    await startInput.setValue('2026-11-10');
    await endInput.setValue('2026-11-05');

    const form = wrapper.find('form.conference-form');
    await form.trigger('submit.prevent');
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 20));

    expect(apiFetchSpy).toHaveBeenCalledWith(
      '/conferencias',
      expect.objectContaining({
        method: 'POST',
        data: {
          name: 'Conferência Duplicada',
          startDate: '2026-11-10',
          endDate: '2026-11-05',
        },
      })
    );

    // Campos foram preservados no formulário
    expect((nameInput.element as HTMLInputElement).value).toBe('Conferência Duplicada');
    expect((startInput.element as HTMLInputElement).value).toBe('2026-11-10');

    // Erros por campo estão visíveis
    expect(wrapper.text()).toContain('O nome já está em uso.');
    expect(wrapper.text()).toContain('Data final deve ser posterior à data inicial.');

    apiFetchSpy.mockRestore();
  });

  it('edicao em Show.vue envia expectedVersion via apiFetch PATCH', async () => {
    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockResolvedValueOnce({
      ...dummyConf,
      name: 'Nome Alterado',
      version: 4,
    });

    const wrapper = mount(Show, {
      props: {
        conference: dummyConf,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // Enter edit mode
    const editBtn = wrapper.find('button.btn-secondary');
    await editBtn.trigger('click');

    const nameInput = wrapper.find('input#edit-name');
    await nameInput.setValue('Nome Alterado');

    const form = wrapper.find('form.edit-form');
    await form.trigger('submit.prevent');
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 20));

    expect(apiFetchSpy).toHaveBeenCalledWith(
      '/conferencias/conf-10',
      expect.objectContaining({
        method: 'PATCH',
        data: expect.objectContaining({
          expectedVersion: 3,
          name: 'Nome Alterado',
        }),
      })
    );

    apiFetchSpy.mockRestore();
  });
});
