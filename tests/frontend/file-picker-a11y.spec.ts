import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FilePicker from '../../resources/js/components/FilePicker.vue';

describe('FilePicker - acessibilidade Axe e interatividade', () => {
  it('input possui label associado e container nao possui role="button" aninhando outros controles', () => {
    const wrapper = mount(FilePicker, {
      props: {
        kind: 'orders',
      },
    });

    // 1. Label check: input must have an id and a label with matching 'for' attribute
    const input = wrapper.find('input[type="file"]');
    expect(input.exists()).toBe(true);
    const inputId = input.attributes('id');
    expect(inputId).toBeDefined();

    const label = wrapper.find(`label[for="${inputId}"]`);
    expect(label.exists()).toBe(true);

    // 2. Nested interactive check: no element with role="button" may contain other buttons or inputs
    const dropzone = wrapper.find('.dropzone');
    expect(dropzone.attributes('role')).not.toBe('button');

    const roleButtons = wrapper.findAll('[role="button"]');
    for (const btn of roleButtons) {
      expect(btn.find('input').exists()).toBe(false);
      expect(btn.find('button').exists()).toBe(false);
    }
  });

  it('permite selecionar e trocar arquivo preservando eventos e acessibilidade', async () => {
    const wrapper = mount(FilePicker, {
      props: {
        kind: 'payouts',
      },
    });

    const file = new File(['order_id,gross\n1,100.00'], 'repasses.csv', { type: 'text/csv' });
    const input = wrapper.find<HTMLInputElement>('input[type="file"]');

    // Trigger file change
    Object.defineProperty(input.element, 'files', {
      value: [file],
      writable: false,
    });
    await input.trigger('change');

    expect(wrapper.emitted('update:modelValue')).toBeTruthy();
    expect(wrapper.emitted('change')).toBeTruthy();

    // Now re-mount or update prop with modelValue
    await wrapper.setProps({ modelValue: file });
    expect(wrapper.find(`label[for="${input.attributes('id')}"]`).exists()).toBe(true);

    // File name must be visible
    expect(wrapper.text()).toContain('repasses.csv');

    // Clear/swap button must exist and be accessible
    const changeBtn = wrapper.find('[data-test="btn-change-file"]');
    expect(changeBtn.exists()).toBe(true);
    await changeBtn.trigger('click');

    expect(wrapper.emitted('update:modelValue')?.some((args) => args[0] === null)).toBe(true);
  });
});
