import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SourceComparison from '../../resources/js/components/SourceComparison.vue';
import type { ResultProjection } from '../../resources/js/core/contracts';

describe('SourceComparison - Pedido ausente', () => {
  it('missingOrder - ausencia de pedido exibe indicativo claro e nao inventa R$ 0,00', () => {
    const resultMissingOrder: ResultProjection = {
      id: 'res-1',
      orderId: 'ORD-999',
      classification: 'missingOrder',
      difference: null,
      reviewStatus: 'pending',
      version: 1,
      order: null, // explicit absence
      payout: {
        line: 12,
        orderId: 'ORD-999',
        payoutId: 'PAY-123',
        businessDate: '2026-10-05',
        gross: '350.00',
        fee: '15.00',
        net: '335.00',
        sourceId: 'src-payouts-1',
        normalizedHash: 'abc123hash',
        displayFilename: 'repasses-outubro.csv',
      },
      reviewEvents: [],
    };

    const wrapper = mount(SourceComparison, {
      props: {
        result: resultMissingOrder,
      },
    });

    // Payout side should show R$ 350,00
    expect(wrapper.text()).toContain('R$ 350,00');

    // Order side must explicitly state absence, and NOT say R$ 0,00 for order gross
    const orderSection = wrapper.find('[data-test="order-details"]');
    expect(orderSection.exists()).toBe(true);
    expect(orderSection.text()).toContain('Pedido não localizado');
    expect(orderSection.text()).not.toContain('R$ 0,00');

    // Difference must be null/empty/dash, not R$ 0,00
    const diffSection = wrapper.find('[data-test="difference-value"]');
    expect(diffSection.text()).not.toContain('R$ 0,00');
  });
});
