import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import DemoControls from '../../resources/js/components/DemoControls.vue';

describe('DemoControls - Modal de reinício da demonstração', () => {
  it('resetfocus - abre modal, captura foco, fecha com Escape e restaura foco no botao de acionamento', async () => {
    // Attach to document.body so document.activeElement works as expected
    const wrapper = mount(DemoControls, {
      props: {
        workspace: {
          id: 'ws-demo-1',
          kind: 'demo',
          expiresAt: '2026-10-11T12:00:00Z',
        },
        demoEnabled: true,
      },
      attachTo: document.body,
    });

    const triggerBtn = wrapper.find<HTMLButtonElement>('[data-test="btn-open-reset-demo"]');
    expect(triggerBtn.exists()).toBe(true);

    // Focus the trigger button first
    triggerBtn.element.focus();
    expect(document.activeElement).toBe(triggerBtn.element);

    // Click to open modal
    await triggerBtn.trigger('click');

    const modal = wrapper.find('[data-test="reset-demo-modal"]');
    expect(modal.exists()).toBe(true);

    // Focus should be inside modal (e.g. cancel or confirm button)
    const modalConfirmBtn = wrapper.find('[data-test="btn-confirm-reset"]');
    const modalCancelBtn = wrapper.find('[data-test="btn-cancel-reset"]');
    expect(modalConfirmBtn.exists()).toBe(true);
    expect(modalCancelBtn.exists()).toBe(true);

    // Active element should be inside modal
    expect(modal.element.contains(document.activeElement)).toBe(true);

    // Press Escape on the window or modal
    const escapeEvent = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
    window.dispatchEvent(escapeEvent);
    await wrapper.vm.$nextTick();

    // Modal should now be closed
    expect(wrapper.find('[data-test="reset-demo-modal"]').exists()).toBe(false);

    // Focus must be restored to trigger button
    expect(document.activeElement).toBe(triggerBtn.element);

    wrapper.unmount();
  });
});
