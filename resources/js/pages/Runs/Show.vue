<script setup lang="ts">
import { ref, computed, watch } from 'vue';
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

// Estado reativo da execução para atualização do resumo de auditoria
const currentRun = ref<RunProjection>(props.run);
let runSummarySeq = 0;
watch(() => props.run, (run) => {
  ++runSummarySeq;
  currentRun.value = run;
  summaryError.value = null;
});

// Lista de resultados local para permitir atualizações instantâneas de revisão sem perder totais
const localResults = ref<ResultProjection[]>(props.results.items);
const summaryError = ref<string | null>(null);
const reloadError = ref<string | null>(null);
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
  reloadError.value = null;
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

async function refreshRunSummary() {
  const seq = ++runSummarySeq;
  try {
    const data = await apiFetch<{
      conference: ConferenceProjection;
      run: RunProjection;
      results: PageOfResults;
      filters: unknown;
    }>(`/execucoes/${props.run.id}`);

    // Protege respostas antigas por sequência
    if (seq === runSummarySeq && data?.run) {
      currentRun.value = data.run;
      summaryError.value = null;
    }
  } catch {
    if (seq === runSummarySeq) summaryError.value = 'Não foi possível atualizar o resumo. A revisão foi salva; tente carregar o andamento novamente.';
  }
}

// Atualização de revisão persistida com sucesso: atualiza item local e recarrega resumo da execução
async function handleReviewSaved(updatedResult: ResultProjection) {
  const index = localResults.value.findIndex((r) => r.id === updatedResult.id);
  if (index !== -1) {
    localResults.value[index] = updatedResult;
  }
  await refreshRunSummary();
}

// Salva e avança para a próxima pendente
function handleSaveAndNext(updatedResult: ResultProjection) {
  const currentId = selectedResultId.value;
  handleReviewSaved(updatedResult);

  // Se o usuário já selecionou outro resultado enquanto o request anterior concluía,
  // não altera a seleção do usuário
  if (currentId === updatedResult.id && nextPendingIndex.value !== -1) {
    selectedResultId.value = localResults.value[nextPendingIndex.value].id;
  }
}

// Recarrega o resultado isolado selecionado via GET /resultados/:id preservando a seleção e nota
async function handleReloadResult() {
  if (!selectedResultId.value) return;
  const requestedId = selectedResultId.value;
  reloadError.value = null;
  try {
    const updatedResult = await apiFetch<ResultProjection>(
      `/resultados/${requestedId}`
    );
    if (updatedResult?.id) {
      const index = localResults.value.findIndex((r) => r.id === updatedResult.id);
      if (index !== -1) {
        localResults.value[index] = updatedResult;
      }
      await refreshRunSummary();
    }
  } catch {
    if (selectedResultId.value === requestedId) reloadError.value = 'Não foi possível carregar a versão atual. Sua nota foi mantida. Tente novamente ou volte à entrada se o ambiente expirou.';
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
            :href="`/execucoes/${currentRun.id}/exportar`"
            class="btn btn-secondary"
            download
          >
            Exportar CSV
          </a>
        </div>
      </header>
      <div v-if="summaryError" class="form-error" role="alert">
        <p>{{ summaryError }}</p>
        <button type="button" class="btn btn-secondary" @click="refreshRunSummary">Atualizar resumo</button>
      </div>

      <!-- Painel de Totais Globais do Servidor (Preservados mesmo com filtros) -->
      <section class="summary-section">
        <div class="summary-grid">
          <div class="summary-card">
            <span class="summary-label">Bruto dos pedidos</span>
            <span class="summary-val tabular-nums">{{ formatMoney(currentRun.summary.orderGross) }}</span>
            <span class="summary-sub tabular-nums">{{ (currentRun.summary.counts.matched || 0) + (currentRun.summary.counts.amountMismatch || 0) + (currentRun.summary.counts.missingPayout || 0) }} registros</span>
          </div>

          <div class="summary-card">
            <span class="summary-label">Bruto dos repasses</span>
            <span class="summary-val tabular-nums">{{ formatMoney(currentRun.summary.payoutGross) }}</span>
            <span class="summary-sub tabular-nums">{{ (currentRun.summary.counts.matched || 0) + (currentRun.summary.counts.amountMismatch || 0) + (currentRun.summary.counts.missingOrder || 0) }} registros</span>
          </div>

          <div class="summary-card">
            <span class="summary-label">Taxas informadas</span>
            <span class="summary-val tabular-nums text-muted">{{ formatMoney(currentRun.summary.fees) }}</span>
            <span class="summary-sub">Informadas no CSV de repasses</span>
          </div>

          <div class="summary-card">
            <span class="summary-label">Líquido informado</span>
            <span class="summary-val tabular-nums font-semibold">{{ formatMoney(currentRun.summary.net) }}</span>
            <span class="summary-sub">Informado no CSV de repasses</span>
          </div>
        </div>

        <div class="divergence-strip">
          <div class="strip-item">
            <span class="strip-label">Correspondências exatas:</span>
            <strong class="tabular-nums text-matched">{{ currentRun.summary.counts.matched || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Divergências de valor:</span>
            <strong class="tabular-nums text-copper">{{ currentRun.summary.counts.amountMismatch || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Repasses ausentes:</span>
            <strong class="tabular-nums text-copper">{{ currentRun.summary.counts.missingPayout || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Pedidos ausentes:</span>
            <strong class="tabular-nums text-copper">{{ currentRun.summary.counts.missingOrder || 0 }}</strong>
          </div>
          <div class="strip-sep">·</div>
          <div class="strip-item">
            <span class="strip-label">Auditoria:</span>
            <span class="tabular-nums font-semibold">
              {{ currentRun.summary.reviewed }} de {{ currentRun.summary.divergences }} divergências revisadas
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
          <div class="table-container result-table-desktop">
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
          <ul class="result-records-mobile" aria-label="Resultados da conciliação">
            <li v-for="item in localResults" :key="item.id">
              <button type="button" class="result-record" :aria-pressed="item.id === selectedResultId" @click="selectResult(item)">
                <strong>Pedido {{ item.orderId }}</strong>
                <span>{{ classificationLabel(item.classification) }}</span>
                <span>Diferença: {{ item.difference === null ? 'Não se aplica' : formatMoney(item.difference) }}</span>
                <span>Revisão: {{ item.reviewStatus === null ? 'Não se aplica' : reviewStatusLabel(item.reviewStatus) }}</span>
                <span class="record-action">{{ item.id === selectedResultId ? 'Registro selecionado' : 'Examinar registro' }}</span>
              </button>
            </li>
            <li v-if="localResults.length === 0" class="empty-results-cell">Nenhum resultado corresponde aos filtros selecionados.</li>
          </ul>

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
          <div v-if="reloadError" class="form-error" role="alert">
            <p>{{ reloadError }}</p>
            <button type="button" class="btn btn-secondary" @click="handleReloadResult">Recarregar versão</button>
            <a href="/entrar" class="btn btn-secondary">Voltar à entrada</a>
          </div>
          <template v-if="currentSelectedResult">
            <!-- Comparação Pedido vs Repasse -->
            <SourceComparison :result="currentSelectedResult" />

            <!-- Painel de Revisão e Auditoria -->
            <ReviewPanel
              :key="currentSelectedResult.id"
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
  border-bottom: 1px solid var(--color-border);
  padding: 0.5rem 0 0.875rem;
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
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.3fr);
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 1024px) {
  .results-layout {
    grid-template-columns: minmax(0, 1fr);
  }
}

.table-column {
  min-width: 0;
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
  min-width: 0;
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

.result-records-mobile {
  display: none;
  list-style: none;
  padding: 0;
  margin: 0;
}

@media (max-width: 640px) {
  .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .strip-item { flex-wrap: wrap; }
  .filter-item, .search-item { min-width: 0; width: 100%; }
  .search-input-wrap .input-text { min-width: 0; }
  .btn-search { flex-shrink: 0; white-space: nowrap; }
  .result-table-desktop { display: none; }
  .result-records-mobile { display: grid; gap: 0.5rem; }
  .result-record {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    width: 100%;
    padding: 0.875rem;
    font: inherit;
    text-align: left;
    color: var(--color-ink);
    background: var(--color-surface);
    border: 0;
    border-bottom: 1px solid var(--color-border);
    border-radius: 0;
    overflow-wrap: anywhere;
  }
  .result-record[aria-pressed="true"] { background: var(--color-page); outline: 1px solid var(--color-ink); outline-offset: -1px; }
  .record-action { font-weight: 600; text-decoration: underline; text-underline-offset: 0.2em; }
  .pagination-bar { flex-wrap: wrap; gap: 0.75rem; }
}
</style>
