<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import SourceComparison from '../../components/SourceComparison.vue';
import ReviewPanel from '../../components/ReviewPanel.vue';
import type {
  ConferenceProjection,
  RunProjection,
  PageOfResults,
  ResultProjection,
  Classification,
  ReviewStatus,
} from '../../core/contracts';
import { formatMoney, classificationLabel, reviewStatusLabel } from '../../core/formatters';
import { apiFetch } from '../../core/api';

const props = defineProps<{
  conference: ConferenceProjection;
  run: RunProjection;
  results: PageOfResults;
  filters: {
    classification?: Classification;
    reviewStatus?: ReviewStatus;
    search?: string;
    page: number;
    pageSize: number;
  };
}>();

// Estado local de filtros (transiente no componente)
const selectedClassification = ref<Classification | ''>(props.filters.classification || '');
const selectedReviewStatus = ref<ReviewStatus | ''>(props.filters.reviewStatus || '');
const searchQuery = ref<string>(props.filters.search || '');

// Lista de resultados local para permitir atualizações instantâneas de revisão sem perder totais
const localResults = ref<ResultProjection[]>(props.results.items);
const selectedResultId = ref<string | null>(
  props.results.items.length > 0 ? props.results.items[0].id : null
);

const currentSelectedResult = computed(() => {
  return localResults.value.find((r) => r.id === selectedResultId.value) || null;
});

const nextPendingIndex = computed(() => {
  if (!currentSelectedResult.value) return -1;
  const currentIndex = localResults.value.findIndex((r) => r.id === selectedResultId.value);
  for (let i = currentIndex + 1; i < localResults.value.length; i++) {
    if (localResults.value[i].reviewStatus === 'pending') {
      return i;
    }
  }
  return -1;
});

const hasNextPending = computed(() => nextPendingIndex.value !== -1);

function selectResult(result: ResultProjection) {
  selectedResultId.value = result.id;
}

function applyFilters() {
  router.get(
    `/execucoes/${props.run.id}`,
    {
      classification: selectedClassification.value || undefined,
      reviewStatus: selectedReviewStatus.value || undefined,
      search: searchQuery.value || undefined,
      page: 1,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const newResults = (page.props.results as PageOfResults)?.items || [];
        localResults.value = newResults;
        if (newResults.length > 0) {
          selectedResultId.value = newResults[0].id;
        } else {
          selectedResultId.value = null;
        }
      },
    }
  );
}

function goToPage(pageNumber: number) {
  router.get(
    `/execucoes/${props.run.id}`,
    {
      classification: selectedClassification.value || undefined,
      reviewStatus: selectedReviewStatus.value || undefined,
      search: searchQuery.value || undefined,
      page: pageNumber,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const newResults = (page.props.results as PageOfResults)?.items || [];
        localResults.value = newResults;
        if (newResults.length > 0) {
          selectedResultId.value = newResults[0].id;
        } else {
          selectedResultId.value = null;
        }
      },
    }
  );
}

// Atualização de revisão persistida com sucesso
function handleReviewSaved(updatedResult: ResultProjection) {
  const index = localResults.value.findIndex((r) => r.id === updatedResult.id);
  if (index !== -1) {
    localResults.value[index] = updatedResult;
  }
}

// Salva e avança para a próxima pendente
function handleSaveAndNext(updatedResult: ResultProjection) {
  handleReviewSaved(updatedResult);
  if (nextPendingIndex.value !== -1) {
    selectedResultId.value = localResults.value[nextPendingIndex.value].id;
  }
}

async function handleReloadResult() {
  if (!selectedResultId.value) return;
  try {
    const updatedPage = await apiFetch<PageOfResults>(
      `/execucoes/${props.run.id}/resultados?page=${props.results.page}&pageSize=${props.results.pageSize}`
    );
    localResults.value = updatedPage.items;
  } catch {
    // Falha silenciosa ou reconexão
  }
}
</script>

<template>
  <AppLayout
    :title="`Resultados — ${conference.name}`"
    :breadcrumbs="[
      { label: 'Conferências', href: '/conferencias' },
      { label: conference.name, href: `/conferencias/${conference.id}` },
      { label: 'Resultados da Conciliação' }
    ]"
  >
    <div class="runs-show-page">
      <!-- Cabeçalho Principal -->
      <header class="run-header">
        <div class="header-titles">
          <h1 class="page-title">Mesa de Resultados da Conciliação</h1>
          <p class="page-subtitle">
            Conferência <strong>{{ conference.name }}</strong> ({{ conference.code }})
          </p>
        </div>

        <div class="header-actions">
          <a
            :href="`/execucoes/${run.id}/exportar`"
            class="btn btn-secondary"
            download
          >
            Exportar CSV
          </a>
        </div>
      </header>

      <!-- Painel de Totais Globais do Servidor (Preservados mesmo com filtros) -->
      <section class="summary-section">
        <div class="summary-grid">
          <div class="summary-card">
            <span class="summary-label">Pedidos faturados (bruto)</span>
            <span class="summary-val tabular-nums">{{ formatMoney(run.summary.orderGross) }}</span>
            <span class="summary-sub tabular-nums">{{ (run.summary.counts.matched || 0) + (run.summary.counts.amountMismatch || 0) + (run.summary.counts.missingPayout || 0) }} registros</span>
          </div>

          <div class="summary-card">
            <span class="summary-label">Repasses informados (bruto)</span>
            <span class="summary-val tabular-nums">{{ formatMoney(run.summary.payoutGross) }}</span>
            <span class="summary-sub tabular-nums">{{ (run.summary.counts.matched || 0) + (run.summary.counts.amountMismatch || 0) + (run.summary.counts.missingOrder || 0) }} registros</span>
          </div>

          <div class="summary-card">
            <span class="summary-label">Tarifas intermediador</span>
            <span class="summary-val tabular-nums text-muted">{{ formatMoney(run.summary.fees) }}</span>
            <span class="summary-sub">Retenção de intermediação</span>
          </div>

          <div class="summary-card">
            <span class="summary-label">Valor líquido apurado</span>
            <span class="summary-val tabular-nums font-semibold">{{ formatMoney(run.summary.net) }}</span>
            <span class="summary-sub">Depósito esperado</span>
          </div>
        </div>

        <div class="divergence-strip">
          <div class="strip-item">
            <span class="strip-label">Correspondências exatas:</span>
            <strong class="tabular-nums text-matched">{{ run.summary.counts.matched || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Divergências de valor:</span>
            <strong class="tabular-nums text-copper">{{ run.summary.counts.amountMismatch || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Repasses ausentes:</span>
            <strong class="tabular-nums text-copper">{{ run.summary.counts.missingPayout || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Pedidos ausentes:</span>
            <strong class="tabular-nums text-copper">{{ run.summary.counts.missingOrder || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Auditoria:</span>
            <span class="tabular-nums font-semibold">
              {{ run.summary.reviewed }} de {{ run.summary.divergences }} divergências revisadas
            </span>
          </div>
        </div>
      </section>

      <!-- Barra de Filtros e Busca -->
      <section class="filters-bar">
        <div class="filter-controls">
          <div class="filter-item">
            <label for="filter-class" class="filter-label">Classificação</label>
            <select
              id="filter-class"
              v-model="selectedClassification"
              class="input-select"
              @change="applyFilters"
            >
              <option value="">Todas as classificações</option>
              <option value="matched">Correspondência exata</option>
              <option value="amountMismatch">Divergência de valor</option>
              <option value="missingPayout">Repasse ausente</option>
              <option value="missingOrder">Pedido ausente</option>
            </select>
          </div>

          <div class="filter-item">
            <label for="filter-review" class="filter-label">Revisão</label>
            <select
              id="filter-review"
              v-model="selectedReviewStatus"
              class="input-select"
              @change="applyFilters"
            >
              <option value="">Todos os status</option>
              <option value="pending">Pendentes</option>
              <option value="reviewed">Revisadas</option>
            </select>
          </div>

          <div class="filter-item search-item">
            <label for="filter-search" class="filter-label">Buscar pedido</label>
            <div class="search-input-wrap">
              <input
                id="filter-search"
                v-model="searchQuery"
                type="text"
                class="input-text"
                placeholder="ID do pedido…"
                @keyup.enter="applyFilters"
              />
              <button type="button" class="btn btn-secondary btn-search" @click="applyFilters">
                Buscar
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Grade Principal: Tabela de Itens e Painel Lateral de Detalhes -->
      <div class="results-layout">
        <!-- Coluna Esquerda: Tabela de Resultados -->
        <div class="table-column">
          <div class="table-container">
            <table class="editorial-table">
              <thead>
                <tr>
                  <th scope="col">ID Pedido</th>
                  <th scope="col">Classificação</th>
                  <th scope="col" class="num-col">Diferença</th>
                  <th scope="col">Revisão</th>
                  <th scope="col" class="num-col">Ação</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in localResults"
                  :key="item.id"
                  :class="{ 'is-selected': item.id === selectedResultId }"
                  @click="selectResult(item)"
                >
                  <td class="tabular-nums font-semibold">{{ item.orderId }}</td>
                  <td>
                    <span
                      class="badge"
                      :class="
                        item.classification === 'matched'
                          ? 'badge-matched'
                          : item.classification === 'amountMismatch'
                          ? 'badge-mismatch'
                          : 'badge-missing'
                      "
                    >
                      {{ classificationLabel(item.classification) }}
                    </span>
                  </td>
                  <td class="num-col tabular-nums">
                    <span
                      :class="{
                        'text-copper font-semibold': item.difference !== null && item.difference !== '0.00',
                        'text-muted': item.difference === null
                      }"
                    >
                      {{ formatMoney(item.difference) }}
                    </span>
                  </td>
                  <td>
                    <span
                      v-if="item.reviewStatus"
                      class="badge"
                      :class="item.reviewStatus === 'reviewed' ? 'badge-neutral' : 'badge-pending'"
                    >
                      {{ reviewStatusLabel(item.reviewStatus) }}
                    </span>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td class="num-col">
                    <button
                      type="button"
                      class="btn btn-quiet btn-select"
                      @click.stop="selectResult(item)"
                    >
                      {{ item.id === selectedResultId ? 'Ativo' : 'Examinar' }}
                    </button>
                  </td>
                </tr>

                <tr v-if="localResults.length === 0">
                  <td colspan="5" class="empty-results-cell">
                    Nenhum resultado corresponde aos filtros selecionados.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginação Simples -->
          <div v-if="results.total > results.pageSize" class="pagination-bar">
            <span class="pagination-info tabular-nums">
              Total de {{ results.total }} registros (página {{ results.page }})
            </span>
            <div class="pagination-actions">
              <button
                type="button"
                class="btn btn-secondary btn-sm"
                :disabled="results.page <= 1"
                @click="goToPage(results.page - 1)"
              >
                Anterior
              </button>
              <button
                type="button"
                class="btn btn-secondary btn-sm"
                :disabled="results.page * results.pageSize >= results.total"
                @click="goToPage(results.page + 1)"
              >
                Próxima
              </button>
            </div>
          </div>
        </div>

        <!-- Coluna Direita: Comparação de Origem e Painel de Revisão -->
        <div class="detail-column">
          <template v-if="currentSelectedResult">
            <!-- Comparação Pedido vs Repasse -->
            <SourceComparison :result="currentSelectedResult" />

            <!-- Painel de Revisão e Auditoria -->
            <ReviewPanel
              :result="currentSelectedResult"
              :expected-version="currentSelectedResult.version"
              :has-next-pending="hasNextPending"
              @saved="handleReviewSaved"
              @save-and-next="handleSaveAndNext"
              @reload-requested="handleReloadResult"
            />
          </template>

          <div v-else class="empty-selection">
            <p>Selecione um registro na lista ao lado para examinar os dados das fontes e registrar uma revisão.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.runs-show-page {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.run-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 1rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--color-border);
}

.page-title {
  font-size: 1.5rem;
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0.25rem 0 0 0;
}

/* Sumário Global */
.summary-section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
  gap: 1rem;
}

.summary-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.summary-label {
  font-size: 0.75rem;
  color: var(--color-muted);
}

.summary-val {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-ink);
}

.summary-sub {
  font-size: 0.6875rem;
  color: var(--color-muted);
}

.divergence-strip {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.625rem;
  font-size: 0.8125rem;
  background-color: var(--color-page);
  border: 1px solid var(--color-border-subtle);
  padding: 0.625rem 1rem;
  border-radius: var(--radius);
}

.strip-item {
  display: flex;
  align-items: baseline;
  gap: 0.375rem;
}

.strip-label {
  color: var(--color-muted);
}

.strip-sep {
  color: var(--color-border);
}

.text-matched {
  color: var(--color-match);
}

.text-copper {
  color: var(--color-copper);
}

/* Filtros */
.filters-bar {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1rem;
}

.filter-controls {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 1rem;
}

.filter-item {
  display: flex;
  flex-direction: column;
  min-width: 11rem;
}

.filter-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-muted);
  margin-bottom: 0.25rem;
}

.search-item {
  flex: 1;
  min-width: 14rem;
}

.search-input-wrap {
  display: flex;
  gap: 0.5rem;
}

.btn-search {
  padding: 0.5rem 0.75rem;
}

/* Grade de Resultados e Detalhes */
.results-layout {
  display: grid;
  grid-template-columns: 1.1fr 1.3fr;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 1024px) {
  .results-layout {
    grid-template-columns: 1fr;
  }
}

.table-column {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 0.5rem;
}

.table-container {
  border-top: none;
}

.is-selected td {
  background-color: #f0f4f8;
}

.btn-select {
  padding: 0.125rem 0.375rem;
  font-size: 0.75rem;
}

.empty-results-cell {
  text-align: center;
  padding: 2rem;
  color: var(--color-muted);
}

.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  border-top: 1px solid var(--color-border-subtle);
}

.pagination-info {
  font-size: 0.75rem;
  color: var(--color-muted);
}

.pagination-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

.detail-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.empty-selection {
  background-color: var(--color-surface);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius);
  padding: 3rem 1.5rem;
  text-align: center;
  color: var(--color-muted);
  font-size: 0.875rem;
}

.font-semibold {
  font-weight: 600;
}

.text-muted {
  color: var(--color-muted);
}
</style>
