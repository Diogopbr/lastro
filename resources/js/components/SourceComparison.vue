<script setup lang="ts">
import { computed } from 'vue';
import type { ResultProjection } from '../core/contracts';
import { formatMoney, formatDate, classificationLabel, reviewStatusLabel } from '../core/formatters';

const props = defineProps<{
  result: ResultProjection;
}>();

const isMatched = computed(() => props.result.classification === 'matched');
const hasDifference = computed(() => props.result.difference !== null && props.result.difference !== '0.00');

const classificationClass = computed(() => {
  switch (props.result.classification) {
    case 'matched':
      return 'badge-matched';
    case 'amountMismatch':
      return 'badge-mismatch';
    case 'missingPayout':
    case 'missingOrder':
      return 'badge-missing';
  }
});
</script>

<template>
  <div class="source-comparison">
    <!-- Cabeçalho de Identificação e Status -->
    <header class="comparison-header">
      <div class="header-main">
        <span class="order-id tabular-nums">Pedido: <strong>{{ result.orderId }}</strong></span>
        <div class="badges-row">
          <span
            data-test="classification-badge"
            class="badge"
            :class="classificationClass"
          >
            {{ classificationLabel(result.classification) }}
          </span>

          <span
            v-if="result.reviewStatus"
            data-test="review-status-badge"
            class="badge"
            :class="result.reviewStatus === 'reviewed' ? 'badge-neutral' : 'badge-pending'"
          >
            {{ reviewStatusLabel(result.reviewStatus) }}
          </span>
        </div>
      </div>

      <div class="header-diff">
        <span class="diff-label">Diferença apurada:</span>
        <span
          data-test="difference-value"
          class="diff-val tabular-nums"
          :class="{ 'has-diff': hasDifference, 'is-zero': isMatched }"
        >
          {{ formatMoney(result.difference) }}
        </span>
      </div>
    </header>

    <!-- Comparação Editorial: Lado a Lado no Desktop, Empilhado e Rotulado no Celular -->
    <div class="comparison-grid">
      <!-- Coluna / Bloco: Fonte Pedidos -->
      <section class="source-panel" data-test="order-details">
        <div class="panel-header">
          <h3 class="panel-title">Fonte: Pedidos</h3>
          <span v-if="result.order" class="panel-tag tabular-nums">Linha {{ result.order.line }}</span>
        </div>

        <div v-if="!result.order" class="absence-box">
          <p class="absence-title">Pedido não localizado</p>
          <p class="absence-desc">O identificador não consta no arquivo de pedidos importado para esta conferência.</p>
        </div>

        <div v-else class="source-fields">
          <div class="field-row">
            <span class="field-label">Valor bruto</span>
            <span class="field-value tabular-nums font-semibold">{{ formatMoney(result.order.gross) }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Data do pedido</span>
            <span class="field-value tabular-nums">{{ formatDate(result.order.businessDate) }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Arquivo de origem</span>
            <span class="field-value file-name" :title="result.order.displayFilename">{{ result.order.displayFilename }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Hash SHA256</span>
            <span class="field-value hash-code tabular-nums" :title="result.order.normalizedHash">
              {{ result.order.normalizedHash.slice(0, 12) }}…
            </span>
          </div>
        </div>
      </section>

      <!-- Coluna / Bloco: Fonte Repasses -->
      <section class="source-panel" data-test="payout-details">
        <div class="panel-header">
          <h3 class="panel-title">Fonte: Repasses</h3>
          <span v-if="result.payout" class="panel-tag tabular-nums">Linha {{ result.payout.line }}</span>
        </div>

        <div v-if="!result.payout" class="absence-box">
          <p class="absence-title">Repasse não localizado</p>
          <p class="absence-desc">O intermediador não informou repasse correspondente para este pedido.</p>
        </div>

        <div v-else class="source-fields">
          <div class="field-row">
            <span class="field-label">Valor bruto informado</span>
            <span class="field-value tabular-nums font-semibold">{{ formatMoney(result.payout.gross) }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Tarifa do intermediador</span>
            <span class="field-value tabular-nums">{{ formatMoney(result.payout.fee) }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Valor líquido apurado</span>
            <span class="field-value tabular-nums">{{ formatMoney(result.payout.net) }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Data do repasse</span>
            <span class="field-value tabular-nums">{{ formatDate(result.payout.businessDate) }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">ID do repasse</span>
            <span class="field-value tabular-nums">{{ result.payout.payoutId || '—' }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Arquivo de origem</span>
            <span class="field-value file-name" :title="result.payout.displayFilename">{{ result.payout.displayFilename }}</span>
          </div>
          <div class="field-row">
            <span class="field-label">Hash SHA256</span>
            <span class="field-value hash-code tabular-nums" :title="result.payout.normalizedHash">
              {{ result.payout.normalizedHash.slice(0, 12) }}…
            </span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.source-comparison {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  overflow: hidden;
  max-width: 100%;
}

.comparison-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 0.875rem 1.25rem;
  background-color: var(--color-page);
  border-bottom: 1px solid var(--color-border);
}

.header-main {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.order-id {
  font-size: 0.9375rem;
  color: var(--color-ink);
}

.badges-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.header-diff {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.diff-label {
  font-size: 0.8125rem;
  color: var(--color-muted);
}

.diff-val {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-ink);
}

.diff-val.has-diff {
  color: var(--color-copper);
}

.diff-val.is-zero {
  color: var(--color-match);
}

/* Grade de Comparação */
.comparison-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  divide-x: 1px solid var(--color-border);
}

.source-panel {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.source-panel:first-child {
  border-right: 1px solid var(--color-border);
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--color-border-subtle);
  padding-bottom: 0.5rem;
}

.panel-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
}

.panel-tag {
  font-size: 0.75rem;
  color: var(--color-muted);
  background-color: var(--color-page);
  padding: 0.125rem 0.375rem;
  border-radius: var(--radius);
}

.absence-box {
  background-color: #fafbfc;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  text-align: center;
}

.absence-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-copper);
  margin: 0 0 0.25rem 0;
}

.absence-desc {
  font-size: 0.75rem;
  color: var(--color-muted);
  margin: 0;
}

.source-fields {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.field-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
  font-size: 0.8125rem;
  padding-bottom: 0.375rem;
  border-bottom: 1px solid var(--color-border-subtle);
}

.field-label {
  color: var(--color-muted);
  flex-shrink: 0;
}

.field-value {
  color: var(--color-ink);
  text-align: right;
  word-break: break-all;
}

.font-semibold {
  font-weight: 600;
}

.hash-code {
  font-family: monospace;
  font-size: 0.75rem;
  color: var(--color-muted);
}

.file-name {
  max-width: 14rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Mobile 390px — Comparação empilhada e rotulada SEM rolagem horizontal */
@media (max-width: 640px) {
  .comparison-grid {
    grid-template-columns: 1fr;
  }

  .source-panel:first-child {
    border-right: none;
    border-bottom: 1px solid var(--color-border);
  }

  .file-name {
    max-width: 10rem;
  }
}
</style>
