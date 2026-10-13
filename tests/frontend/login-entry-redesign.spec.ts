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
        auth: { demoEnabled: true, user: null, workspace: null },
        flash: { success: null },
      },
      url: '/entrar',
    }),
  };
});

describe('Login - Redesenho Editorial da Entrada Demo', () => {
  it('no modo demo exibe composicao editorial com titulo aprovado, exemplo ficticio concreto e oculta credenciais normais', () => {
    const wrapper = mount(Login, {
      props: {
        demoEnabled: true,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // 1. Título editorial e botão da demo
    expect(wrapper.text()).toContain('Pedidos e repasses, conferidos linha a linha.');
    expect(wrapper.find('[data-test="btn-start-demo-login"]').exists()).toBe(true);

    // 2. Não deve exibir campos de login de equipe no modo demo
    expect(wrapper.find('input#email').exists()).toBe(false);
    expect(wrapper.find('input#password').exists()).toBe(false);

    // 3. Exemplo fictício concreto
    const exampleSection = wrapper.find('[data-test="demo-concrete-example"]');
    expect(exampleSection.exists()).toBe(true);
    expect(exampleSection.text()).toContain('0002');
    expect(exampleSection.text()).toContain('R$ 200,00');
    expect(exampleSection.text()).toContain('R$ 190,00');
    expect(exampleSection.text()).toContain('-R$ 10,00');
    expect(exampleSection.text()).toContain('R$ 5,00');
    expect(exampleSection.text()).toContain('R$ 185,00');
    expect(exampleSection.text()).toContain('Linha 3 de pedidos-exemplo.csv');
    expect(exampleSection.text()).toContain('Linha 3 de repasses-exemplo.csv');

    // Lede não afirma detecção de retenções indevidas
    expect(wrapper.text()).not.toContain('retenções de tarifas');
    expect(wrapper.text()).toContain('Compara o valor bruto de cada pedido');

    // 4. Explicação de cota/validade
    expect(wrapper.text()).toContain('5 conferências');
    expect(wrapper.text()).toContain('24h');
  });

  it('no modo normal (demoEnabled false) exibe formulario de credenciais e oculta demo', () => {
    const wrapper = mount(Login, {
      props: {
        demoEnabled: false,
      },
      global: {
        stubs: { Head: true, Link: true },
      },
    });

    // Exibe campos de e-mail e senha
    expect(wrapper.find('input#email').exists()).toBe(true);
    expect(wrapper.find('input#password').exists()).toBe(true);

    // Não exibe botão de iniciar demo
    expect(wrapper.find('[data-test="btn-start-demo-login"]').exists()).toBe(false);
  });
});
