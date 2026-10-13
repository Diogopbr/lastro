<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import type { ConferenceProjection, RunProjection } from '../../core/contracts';
import { formatDate, formatDateTime, formatMoney } from '../../core/formatters';
import { apiFetch } from '../../core/api';

const props = defineProps<{
  conference: ConferenceProjection;
}>();

const currentConference = ref<ConferenceProjection>(props.conference);
const isEditing = ref(false);

const hasSources = computed(
  () => !!currentConference.value.sources.orders || !!currentConference.value.sources.payouts
);

const bothSourcesConfirmed = computed(
  () => !!currentConference.value.sources.orders && !!currentConference.value.sources.payouts
);

const editName = ref(props.conference.name);
const editStartDate = ref(props.conference.startDate);
const editEndDate = ref(props.conference.endDate);

const isUpdating = ref(false);
const updateErrors = ref<Record<string, string>>({});
const updateGeneralError = ref<string | null>(null);

const isReconciling = ref(false);
const reconcileError = ref<string | null>(null);

async function handleUpdateConference() {
  isUpdating.value = true;
  updateErrors.value = {};
  updateGeneralError.value = null;

  try {
    const updated = await apiFetch<ConferenceProjection>(
      `/conferencias/${currentConference.value.id}`,
      {
        method: 'PATCH',
        data: {
          expectedVersion: currentConference.value.version,
          name: editName.value,
          startDate: editStartDate.value,
          endDate: editEndDate.value,
        },
      }
    );

    currentConference.value = updated;
    isEditing.value = false;
  } catch (err: unknown) {
    const errorObj = err as { message?: string; errors?: Record<string, string[] | string> };
    if (errorObj?.errors) {
      const mapped: Record<string, string> = {};
      for (const [key, val] of Object.entries(errorObj.errors)) {
        mapped[key] = Array.isArray(val) ? val[0] : String(val);
      }
      updateErrors.value = mapped;
    }
    updateGeneralError.value = errorObj?.message || 'Falha ao atualizar conferência.';
  } finally {
    isUpdating.value = false;
  }
}

async function handleReconcile() {
  isReconciling.value = true;
  reconcileError.value = null;

  try {
    const run = await apiFetch<RunProjection>(
      `/conferencias/${currentConference.value.id}/conciliar`,
      {
        method: 'POST',
      }
    );

    window.location.href = `/execucoes/${run.id}`;
  } catch (err: unknown) {
    const errorObj = err as { message?: string };
    reconcileError.value = errorObj?.message || 'Falha ao executar conciliação.';
  } finally {
    isReconciling.value = false;
  }
}
</script>

<template>
  <AppLayout
    :title="currentConference.name"
    :breadcrumbs="[
      { label: 'Conferências', href: '/conferencias' },
      { label: currentConference.name }
    ]"
  >
    <div class="conference-show">
      <!-- Cabeçalho da Conferência -->
      <header class="conference-header">
        <div class="header-main">
          <div class="code-badge tabular-nums">{{ currentConference.code }}</div>
          <div>
            <h1 class="conf-title">{{ currentConference.name }}</h1>
            <p class="conf-period tabular-nums">
              Período de competência:
              <strong>{{ formatDate(currentConference.startDate) }}</strong> a
              <strong>{{ formatDate(currentConference.endDate) }}</strong>
            </p>
          </div>
        </div>

        <div class="header-actions">
          <button
            v-if="!isEditing"
            type="button"
            class="btn btn-secondary"
            @click="isEditing = true"
          >
            Editar dados
          </button>
        </div>
      </header>

      <!-- Formulário de Edição da Conferência (Período bloqueado após primeira fonte) -->
      <section v-if="isEditing" class="edit-box">
        <h2 class="box-title">Editar conferência</h2>

        <div v-if="updateGeneralError" class="form-error" role="alert">
          {{ updateGeneralError }}
        </div>

        <form @submit.prevent="handleUpdateConference" class="edit-form">
          <div class="form-grid">
            <div class="form-group">
              <label for="edit-name" class="form-label">Nome</label>
              <input
                id="edit-name"
                v-model="editName"
                type="text"
                class="input-text"
                required
                :disabled="isUpdating"
              />
              <span v-if="updateErrors.name" class="form-error">{{ updateErrors.name }}</span>
            </div>

            <div class="form-group">
              <label for="edit-start" class="form-label">Data inicial</label>
              <input
                id="edit-start"
                v-model="editStartDate"
                type="date"
                class="input-text"
                required
                :disabled="hasSources || isUpdating"
              />
              <span v-if="hasSources" class="form-hint">
                Bloqueado após o envio de fontes.
              </span>
              <span v-if="updateErrors.startDate" class="form-error">{{ updateErrors.startDate }}</span>
            </div>

            <div class="form-group">
              <label for="edit-end" class="form-label">Data final</label>
              <input
                id="edit-end"
                v-model="editEndDate"
                type="date"
                class="input-text"
                required
                :disabled="hasSources || isUpdating"
              />
              <span v-if="hasSources" class="form-hint">
                Bloqueado após o envio de fontes.
              </span>
              <span v-if="updateErrors.endDate" class="form-error">{{ updateErrors.endDate }}</span>
            </div>
          </div>

          <div class="form-actions">
            <button
              type="button"
              class="btn btn-secondary"
              :disabled="isUpdating"
              @click="isEditing = false"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isUpdating"
            >
              {{ isUpdating ? 'Salvando…' : 'Salvar alterações' }}
            </button>
          </div>
        </form>
      </section>

      <!-- Grade de Fontes: Pedidos e Repasses -->
      <section class="sources-section">
        <h2 class="section-title">Fontes de dados</h2>
        <div class="sources-grid">
          <!-- Fonte: Pedidos -->
          <div class="source-card">
            <div class="card-header">
              <h3 class="card-title">1. Fonte de Pedidos</h3>
              <span
                class="badge"
                :class="currentConference.sources.orders ? 'badge-matched' : 'badge-pending'"
              >
                {{ currentConference.sources.orders ? 'Confirmada' : 'Pendente' }}
              </span>
            </div>

            <div v-if="currentConference.sources.orders" class="card-body">
              <div class="meta-row">
                <span class="meta-label">Arquivo:</span>
                <span class="meta-val font-semibold">{{ currentConference.sources.orders.displayFilename }}</span>
              </div>
              <div class="meta-row">
                <span class="meta-label">Total de registros:</span>
                <span class="meta-val tabular-nums">{{ currentConference.sources.orders.rowCount }}</span>
              </div>
              <div class="meta-row">
                <span class="meta-label">Valor bruto total:</span>
                <span class="meta-val tabular-nums font-semibold">{{ formatMoney(currentConference.sources.orders.grossTotal) }}</span>
              </div>
              <div class="meta-row">
                <span class="meta-label">Confirmado em:</span>
                <span class="meta-val tabular-nums">{{ formatDateTime(currentConference.sources.orders.confirmedAt) }}</span>
              </div>

              <p class="source-immutable-hint">
                Fontes confirmadas são imutáveis. O reenvio do mesmo arquivo é idempotente; para conferir arquivos com conteúdo diferente, crie uma nova conferência.
              </p>
              <div class="card-action">
                <Link
                  :href="`/conferencias/${currentConference.id}/fontes/orders/preview`"
                  class="btn btn-secondary btn-sm"
                >
                  Conferir arquivo novamente
                </Link>
              </div>
            </div>

            <div v-else class="card-empty">
              <p class="empty-text">Importe o arquivo CSV contendo os pedidos faturados no período.</p>
              <Link
                :href="`/conferencias/${currentConference.id}/fontes/orders/preview`"
                class="btn btn-primary"
              >
                Importar pedidos (.csv)
              </Link>
            </div>
          </div>

          <!-- Fonte: Repasses -->
          <div class="source-card">
            <div class="card-header">
              <h3 class="card-title">2. Fonte de Repasses</h3>
              <span
                class="badge"
                :class="currentConference.sources.payouts ? 'badge-matched' : 'badge-pending'"
              >
                {{ currentConference.sources.payouts ? 'Confirmada' : 'Pendente' }}
              </span>
            </div>

            <div v-if="currentConference.sources.payouts" class="card-body">
              <div class="meta-row">
                <span class="meta-label">Arquivo:</span>
                <span class="meta-val font-semibold">{{ currentConference.sources.payouts.displayFilename }}</span>
              </div>
              <div class="meta-row">
                <span class="meta-label">Total de registros:</span>
                <span class="meta-val tabular-nums">{{ currentConference.sources.payouts.rowCount }}</span>
              </div>
              <div class="meta-row">
                <span class="meta-label">Valor bruto total:</span>
                <span class="meta-val tabular-nums font-semibold">{{ formatMoney(currentConference.sources.payouts.grossTotal) }}</span>
              </div>
              <div class="meta-row">
                <span class="meta-label">Confirmado em:</span>
                <span class="meta-val tabular-nums">{{ formatDateTime(currentConference.sources.payouts.confirmedAt) }}</span>
              </div>

              <p class="source-immutable-hint">
                Fontes confirmadas são imutáveis. O reenvio do mesmo arquivo é idempotente; para conferir arquivos com conteúdo diferente, crie uma nova conferência.
              </p>
              <div class="card-action">
                <Link
                  :href="`/conferencias/${currentConference.id}/fontes/payouts/preview`"
                  class="btn btn-secondary btn-sm"
                >
                  Conferir arquivo novamente
                </Link>
              </div>
            </div>

            <div v-else class="card-empty">
              <p class="empty-text">Importe o arquivo CSV gerado pelo intermediador financeiro.</p>
              <Link
                :href="`/conferencias/${currentConference.id}/fontes/payouts/preview`"
                class="btn btn-primary"
              >
                Importar repasses (.csv)
              </Link>
            </div>
          </div>
        </div>
      </section>

      <!-- Seção de Conciliação e Resultados -->
      <section class="reconciliation-section">
        <div class="reconcile-card">
          <div class="reconcile-header">
            <div>
              <h2 class="reconcile-title">Execução da conciliação</h2>
              <p class="reconcile-desc">
                Cruza os pedidos e repasses pelo identificador <code>order_id</code> e classifica
                correspondências, divergências e ausências.
              </p>
            </div>

            <div class="reconcile-controls">
              <button
                type="button"
                class="btn btn-primary"
                data-test="btn-run-reconciliation"
                :disabled="!bothSourcesConfirmed || isReconciling"
                @click="handleReconcile"
              >
                {{ isReconciling ? 'Processando…' : currentConference.run ? 'Reexecutar conciliação' : 'Executar conciliação' }}
              </button>
            </div>
          </div>

          <div v-if="reconcileError" class="form-error" role="alert">
            {{ reconcileError }}
          </div>

          <div v-if="currentConference.run" class="run-info-box">
            <div class="run-stats">
              <span class="run-badge badge badge-neutral">Execução vigente</span>
              <span v-if="currentConference.progress" class="progress-details tabular-nums">
                <strong>{{ currentConference.progress.divergences }}</strong> divergências encontradas
                (<strong>{{ currentConference.progress.reviewed }}</strong> revisadas)
              </span>
            </div>

            <Link
              :href="`/execucoes/${currentConference.run.id}`"
              class="btn btn-secondary"
            >
              Ver mesa de resultados da conciliação
            </Link>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.conference-show {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.conference-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--color-border);
}

.header-main {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.code-badge {
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-ink);
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  padding: 0.375rem 0.625rem;
  border-radius: var(--radius);
}

.conf-title {
  font-size: 1.625rem;
  margin: 0;
}

.conf-period {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0.25rem 0 0 0;
}

.edit-box {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.box-title {
  font-size: 1.0625rem;
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1rem;
}

.sources-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.section-title {
  font-size: 1.125rem;
  margin: 0;
}

.sources-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .sources-grid {
    grid-template-columns: 1fr;
  }
}

.source-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--color-border-subtle);
  padding-bottom: 0.625rem;
}

.card-title {
  font-size: 0.9375rem;
  margin: 0;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.8125rem;
}

.meta-label {
  color: var(--color-muted);
}

.meta-val {
  color: var(--color-ink);
}

.card-action {
  margin-top: 0.5rem;
}

.card-empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1.5rem 0;
}

.empty-text {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0;
}

.reconcile-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.reconcile-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.reconcile-title {
  font-size: 1.0625rem;
  margin: 0;
}

.reconcile-desc {
  font-size: 0.8125rem;
  color: var(--color-muted);
  margin: 0.25rem 0 0 0;
}

.run-info-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  background-color: var(--color-page);
  border: 1px solid var(--color-border-subtle);
  padding: 0.875rem 1rem;
  border-radius: var(--radius);
}

.run-stats {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.progress-details {
  font-size: 0.8125rem;
  color: var(--color-ink);
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
}

.font-semibold {
  font-weight: 600;
}

.source-immutable-hint {
  font-size: 0.75rem;
  color: var(--color-muted);
  margin: 0.5rem 0 0.25rem 0;
  line-height: 1.4;
}

@media (max-width: 640px) {
  .conference-header {
    flex-direction: column;
    align-items: stretch;
    gap: 0.75rem;
  }
  .header-actions {
    width: 100%;
  }
  .header-actions .btn {
    width: 100%;
  }
  .sources-grid {
    grid-template-columns: 1fr;
  }
}
</style>
