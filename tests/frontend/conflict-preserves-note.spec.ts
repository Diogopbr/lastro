import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ReviewPanel from '../../resources/js/components/ReviewPanel.vue';
import type { ResultProjection } from '../../resources/js/core/contracts';

describe('ReviewPanel - Tratamento de conflito 409', () => {
  const result: ResultProjection = {
    id: 'res-div-1',
    orderId: 'ORD-500',
    classification: 'amountMismatch',
    difference: '15.00',
    reviewStatus: 'pending',
    version: 1,
    order: null,
    payout: null,
    reviewEvents: [],
  };

  it('conflito preservesnote e oferece opcao de recarregar versao', async () => {
    // Mock saveReview function that rejects with 409 conflict
    const onSave = vi.fn().mockRejectedValue({
      status: 409,
      code: 'conflict',
      message: 'Versão desatualizada. O registro foi modificado por outro usuário.',
    });

    const wrapper = mount(ReviewPanel, {
      props: {
        result,
        expectedVersion: 1,
        onSave,
      },
    });

    const textarea = wrapper.find('textarea[data-test="review-note-input"]');
    expect(textarea.exists()).toBe(true);

    const typedNote = 'Minha anotação crítica sobre o repasse';
    await textarea.setValue(typedNote);

    const submitBtn = wrapper.find('[data-test="btn-save-review"]');
    await submitBtn.trigger('click');

    // Wait for promise resolution/rejection
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 50));

    // The typed note must be preserved in the textarea
    expect((textarea.element as HTMLTextAreaElement).value).toBe(typedNote);

    // Conflict error message must be shown
    const errorEl = wrapper.find('[data-test="conflict-banner"]');
    expect(errorEl.exists()).toBe(true);
    expect(errorEl.text().toLowerCase()).toContain('conflito');

    // Button to reload/update version must be offered
    const reloadBtn = wrapper.find('[data-test="btn-reload-version"]');
    expect(reloadBtn.exists()).toBe(true);
  });
});
