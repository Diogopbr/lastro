import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ReviewPanel from '../../resources/js/components/ReviewPanel.vue';
import type { ResultProjection } from '../../resources/js/core/contracts';
import * as apiModule from '../../resources/js/core/api';

describe('ReviewPanel - default expectedVersion', () => {
  it('usa result.version quando a prop expectedVersion nao for enviada', async () => {
    const result: ResultProjection = {
      id: 'res-ver-1',
      orderId: 'ORD-77',
      classification: 'amountMismatch',
      difference: '5.00',
      reviewStatus: 'pending',
      version: 4, // version is 4!
      order: null,
      payout: null,
      reviewEvents: [],
    };

    const apiFetchSpy = vi.spyOn(apiModule, 'apiFetch').mockResolvedValue({
      ...result,
      reviewStatus: 'reviewed',
      version: 5,
    });

    // Mount without passing expectedVersion prop
    const wrapper = mount(ReviewPanel, {
      props: {
        result,
      },
    });

    const textarea = wrapper.find('textarea[data-test="review-note-input"]');
    await textarea.setValue('Análise da divergência');

    // Submit review
    const submitBtn = wrapper.find('[data-test="btn-save-review"]');
    await submitBtn.trigger('click');

    await wrapper.vm.$nextTick();

    // Verify apiFetch was called with expectedVersion: 4 (from result.version, not default 1!)
    expect(apiFetchSpy).toHaveBeenCalledWith(
      '/resultados/res-ver-1/revisoes',
      expect.objectContaining({
        data: expect.objectContaining({
          expectedVersion: 4,
          action: 'reviewed',
          note: 'Análise da divergência',
        }),
      })
    );

    apiFetchSpy.mockRestore();
  });
});
