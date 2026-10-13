import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Login from '../../resources/js/pages/Auth/Login.vue';

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
      url: '/entrar',
    }),
  };
});

describe('Login - controle de modo demo', () => {
  it('nao exibe opcao de demonstracao quando demoEnabled for falso ou omitido por default', () => {
    const wrapper = mount(Login, {
      props: {},
      global: {
        stubs: {
          Head: true,
          Link: true,
        },
      },
    });

    expect(wrapper.find('[data-test="btn-start-demo-login"]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Iniciar demonstração privada');
  });

  it('exibe opcao de demonstracao quando demoEnabled for explicitamente verdadeiro', () => {
    const wrapper = mount(Login, {
      props: {
        demoEnabled: true,
      },
      global: {
        stubs: {
          Head: true,
          Link: true,
        },
      },
    });

    expect(wrapper.find('[data-test="btn-start-demo-login"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('Pedidos e repasses, conferidos linha a linha.');
    expect(wrapper.text()).toContain('Iniciar demonstração privada');
  });
});
