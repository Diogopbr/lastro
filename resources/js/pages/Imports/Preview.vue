<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import FilePicker from '../../components/FilePicker.vue';
import type { ConferenceProjection, SourceKind, ImportPreview, SourceProjection } from '../../core/contracts';
import { apiFetch } from '../../core/api';
import { formatMoney, formatDate } from '../../core/formatters';

const props = defineProps<{
  conference: ConferenceProjection;
  kind: SourceKind;
  preview: ImportPreview | null;
}>();

const kindTitle = props.kind === 'orders' ? 'Pedidos' : 'Repasses';
const currentConference = ref<ConferenceProjection>(props.conference);
const selectedFile = ref<File | null>(null);
const currentPreview = ref<ImportPreview | null>(props.preview);

const isGeneratingPreview = ref(false);
const isConfirming = ref(false);
const isRefreshingConference = ref(false);
const uploadError = ref<string | null>(null);
const confirmError = ref<string | null>(null);
const is409Conflict = ref(false);
const is404Expired = ref(false);

const canConfirm = computed(() => {
  return !!currentPreview.value && currentPreview.value.valid === true;
});

function handleFileChange(file: File | null) {
  selectedFile.value = file;
  // Ao trocar de arquivo, limpa a prévia anterior e erros transitórios
  currentPreview.value = null;
  uploadError.value = null;
  confirmError.value = null;
  is409Conflict.value = false;
  is404Expired.value = false;
}

async function handleGeneratePreview() {
  if (!selectedFile.value) return;

  isGeneratingPreview.value = true;
  uploadError.value = null;

  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);

    const result = await apiFetch<ImportPreview>(
      `/conferencias/${currentConference.value.id}/fontes/${props.kind}/preview`,
      {
        method: 'POST',
        data: formData,
      }
    );

    currentPreview.value = result;
  } catch (err: unknown) {
    const errorObj = err as { message?: string; status?: number; code?: string; errors?: Record<string, string[]> };
    if (errorObj?.status === 404 || errorObj?.code === 'not_found') {
      is404Expired.value = true;
      uploadError.value =
        errorObj?.message ||
        'A conferência não foi encontrada ou o ambiente de demonstração expirou/foi reiniciado.';
    } else {
      uploadError.value = errorObj?.message || 'Falha ao processar arquivo para prévia.';
    }
  } finally {
    isGeneratingPreview.value = false;
  }
}

async function handleRefreshConferenceAndPreview() {
  isRefreshingConference.value = true;
  confirmError.value = null;

  try {
    // 1. Busca dados frescos da conferência (nova versão e eventual período atualizado)
    const freshConf = await apiFetch<ConferenceProjection>(
      `/conferencias/${currentConference.value.id}`
    );
    currentConference.value = freshConf;
    is409Conflict.value = false;

    // 2. Preserva o arquivo selecionado e recalcula a prévia para o novo estado
    if (selectedFile.value) {
      await handleGeneratePreview();
    }
  } catch (err: unknown) {
    const errorObj = err as { message?: string; status?: number; code?: string };
    if (errorObj?.status === 404 || errorObj?.code === 'not_found') {
      is404Expired.value = true;
      confirmError.value =
        errorObj?.message ||
        'A conferência não foi encontrada ou o ambiente de demonstração expirou/foi reiniciado.';
    } else {
      confirmError.value = errorObj?.message || 'Falha ao atualizar dados da conferência.';
    }
  } finally {
    isRefreshingConference.value = false;
  }
}

async function handleConfirmImport() {
  if (!selectedFile.value || !currentPreview.value || !currentPreview.value.valid) return;

  isConfirming.value = true;
  confirmError.value = null;
  is409Conflict.value = false;
  is404Expired.value = false;

  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('expectedHash', currentPreview.value.normalizedHash || '');
    formData.append('expectedVersion', String(currentConference.value.version));

    await apiFetch<SourceProjection>(
      `/conferencias/${currentConference.value.id}/fontes/${props.kind}/confirm`,
      {
        method: 'POST',
        data: formData,
      }
    );

    // Sucesso: redireciona para a tela da conferência
    window.location.href = `/conferencias/${currentConference.value.id}`;
  } catch (err: unknown) {
    const errorObj = err as { message?: string; status?: number; code?: string };
    // Arquivo selecionado é preservado intacto em caso de 422/409/404
    if (errorObj?.status === 409 || errorObj?.code === 'conflict') {
      is409Conflict.value = true;
      confirmError.value =
        errorObj?.message ||
        'Conflito de versão: a conferência foi modificada. Atualize a versão e revalide a prévia.';
    } else if (errorObj?.status === 404 || errorObj?.code === 'not_found') {
      is404Expired.value = true;
      confirmError.value =
        'A conferência não foi encontrada ou o ambiente de demonstração expirou/foi reiniciado.';
    } else {
      confirmError.value =
        errorObj?.message ||
        'Erro ao confirmar importação. O arquivo permanece selecionado para nova tentativa.';
    }
  } finally {
    isConfirming.value = false;
  }
}

async function handleStartNewDemo() {
  try {
    const res = await apiFetch<{ redirect?: string }>('/demo/start', { method: 'POST' });
    if (res?.redirect) {
      window.location.href = res.redirect;
      return;
    }
  } catch {
    // fallback
  }
  window.location.href = '/entrar';
}
</script>

<template>
  <AppLayout
    :title="`Importar ${kindTitle} — ${currentConference.name}`"
    :breadcrumbs="[
      { label: 'Conferências', href: '/conferencias' },
      { label: currentConference.name, href: `/conferencias/${currentConference.id}` },
      { label: `Importar ${kindTitle}` }
    ]"
  >
    <div class="import-preview-page">
      <header class="page-header">
        <h1 class="page-title">Importar Fonte: {{ kindTitle }}</h1>
        <p class="page-subtitle">
          Confira as linhas e valores apurados antes de confirmar a importação para a conferência.
        </p>
      </header>

      <!-- Etapa 1: Seleção do Arquivo CSV -->
      <section class="upload-section">
        <h2 class="section-title">1. Seleção do arquivo CSV</h2>
        <div class="picker-wrapper">
          <FilePicker
            :kind="kind"
            :model-value="selectedFile"
            :disabled="isGeneratingPreview || isConfirming || isRefreshingConference"
            :error="uploadError || ''"
            @update:model-value="handleFileChange"
            @change="handleFileChange"
          />

          <div class="picker-actions">
            <button
              type="button"
              class="btn btn-primary"
              :disabled="!selectedFile || isGeneratingPreview || isConfirming || isRefreshingConference"
              @click="handleGeneratePreview"
            >
              {{ isGeneratingPreview ? 'Analisando arquivo…' : 'Gerar prévia' }}
            </button>
          </div>
        </div>
      </section>

      <!-- Erro ao Confirmar / Conflito 409 / 404 Expirado -->
      <div v-if="confirmError" class="form-error alert-box" role="alert">
        <div class="alert-msg">
          <strong>Atenção:</strong> {{ confirmError }}
        </div>
        <div v-if="is404Expired" class="mt-2">
          <button
            type="button"
            class="btn btn-primary btn-sm"
            data-test="btn-start-new-demo-after-404"
            @click="handleStartNewDemo"
          >
            Iniciar nova demonstração privada
          </button>
        </div>
        <button
          v-else-if="is409Conflict"
          type="button"
          class="btn btn-secondary btn-sm mt-2"
          data-test="btn-refresh-conference-version"
          :disabled="isRefreshingConference"
          @click="handleRefreshConferenceAndPreview"
        >
          {{ isRefreshingConference ? 'Atualizando conferência…' : 'Atualizar versão da conferência e recalcular prévia' }}
        </button>
      </div>

      <!-- Etapa 2: Prévia e Validação de Linhas -->
      <section v-if="currentPreview" class="preview-section">
        <div class="preview-header">
          <div>
            <h2 class="section-title">2. Resultado da prévia e validação</h2>
            <p class="preview-meta tabular-nums">
              Total de registros: <strong>{{ currentPreview.rowCount }}</strong> |
              Valor bruto acumulado: <strong>{{ formatMoney(currentPreview.grossTotal) }}</strong>
            </p>
          </div>

          <div class="preview-status-pill">
            <span
              class="badge"
              :class="currentPreview.valid ? 'badge-matched' : 'badge-mismatch'"
            >
              {{ currentPreview.valid ? 'Arquivo válido para importação' : 'Erros de validação detectados' }}
            </span>
          </div>
        </div>

        <!-- Lista de Erros por Linha -->
        <div v-if="currentPreview.errors && currentPreview.errors.length > 0" class="errors-box">
          <h3 class="errors-title">
            {{ currentPreview.errors.length }} inconsistência(s) encontrada(s):
          </h3>
          <ul class="errors-list">
            <li
              v-for="(err, idx) in currentPreview.errors"
              :key="idx"
              class="error-item"
            >
              <span class="err-line tabular-nums">Linha {{ err.line }}:</span>
              <span class="err-field">[{{ err.field }}]</span>
              <span class="err-msg">{{ err.message }}</span>
            </li>
          </ul>
        </div>

        <!-- Tabela de Prévia (Desktop) -->
        <div class="table-container preview-table-desktop">
          <table class="editorial-table">
            <thead>
              <tr>
                <th scope="col">Linha</th>
                <th scope="col">ID Pedido</th>
                <th v-if="kind === 'payouts'" scope="col">ID Repasse</th>
                <th scope="col">Data</th>
                <th scope="col" class="num-col">Bruto</th>
                <th v-if="kind === 'payouts'" scope="col" class="num-col">Tarifa</th>
                <th v-if="kind === 'payouts'" scope="col" class="num-col">Líquido</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in currentPreview.rows" :key="row.line">
                <td class="tabular-nums text-muted">{{ row.line }}</td>
                <td class="tabular-nums font-semibold">{{ row.orderId }}</td>
                <td v-if="kind === 'payouts'" class="tabular-nums text-muted">{{ row.payoutId || '—' }}</td>
                <td class="tabular-nums">{{ formatDate(row.businessDate) }}</td>
                <td class="num-col font-semibold">{{ formatMoney(row.gross) }}</td>
                <td v-if="kind === 'payouts'" class="num-col text-muted">{{ formatMoney(row.fee) }}</td>
                <td v-if="kind === 'payouts'" class="num-col font-semibold">{{ formatMoney(row.net) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Registros Rotulados (Mobile <= 640px) sem rolagem horizontal -->
        <div class="preview-records-mobile">
          <div
            v-for="row in currentPreview.rows"
            :key="`mobile-${row.line}`"
            class="mobile-record-card"
          >
            <div class="mobile-record-header">
              <span class="record-line tabular-nums">Linha {{ row.line }}</span>
              <span class="record-id tabular-nums font-semibold">{{ row.orderId }}</span>
            </div>
            <div class="mobile-record-body">
              <div v-if="kind === 'payouts' && row.payoutId" class="record-row">
                <span class="record-label">ID Repasse:</span>
                <span class="record-val tabular-nums">{{ row.payoutId }}</span>
              </div>
              <div class="record-row">
                <span class="record-label">Data:</span>
                <span class="record-val tabular-nums">{{ formatDate(row.businessDate) }}</span>
              </div>
              <div class="record-row">
                <span class="record-label">Bruto:</span>
                <span class="record-val tabular-nums font-semibold">{{ formatMoney(row.gross) }}</span>
              </div>
              <div v-if="kind === 'payouts'" class="record-row">
                <span class="record-label">Tarifa:</span>
                <span class="record-val tabular-nums text-muted">{{ formatMoney(row.fee) }}</span>
              </div>
              <div v-if="kind === 'payouts'" class="record-row">
                <span class="record-label">Líquido:</span>
                <span class="record-val tabular-nums font-semibold">{{ formatMoney(row.net) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Ação Final de Confirmação -->
        <div class="confirm-bar">
          <Link
            :href="`/conferencias/${conference.id}`"
            class="btn btn-secondary"
            :disabled="isConfirming"
          >
            Voltar para a conferência
          </Link>

          <button
            type="button"
            class="btn btn-primary"
            data-test="btn-confirm-import"
            :disabled="!canConfirm || isConfirming"
            @click="handleConfirmImport"
          >
            {{ isConfirming ? 'Confirmando importação…' : 'Confirmar importação' }}
          </button>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.import-preview-page {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.page-header {
  border-bottom: 1px solid var(--color-border);
  padding-bottom: 1rem;
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

.upload-section {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.section-title {
  font-size: 1rem;
  margin: 0;
}

.picker-wrapper {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.picker-actions {
  display: flex;
  justify-content: flex-end;
}

.preview-section {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.preview-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 1rem;
}

.preview-meta {
  font-size: 0.8125rem;
  color: var(--color-muted);
  margin: 0.25rem 0 0 0;
}

.errors-box {
  background-color: var(--color-copper-subtle);
  border: 1px solid var(--color-copper-border);
  border-radius: var(--radius);
  padding: 1rem;
}

.errors-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-copper);
  margin: 0 0 0.5rem 0;
}

.errors-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  font-size: 0.8125rem;
}

.error-item {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  color: var(--color-copper);
}

.err-line {
  font-weight: 600;
}

.err-field {
  font-family: monospace;
}

.confirm-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  border-top: 1px solid var(--color-border-subtle);
  padding-top: 1rem;
}

.alert-box {
  padding: 0.75rem 1rem;
  background-color: var(--color-copper-subtle);
  border: 1px solid var(--color-copper-border);
  border-radius: var(--radius);
}

.text-muted {
  color: var(--color-muted);
}

.font-semibold {
  font-weight: 600;
}

.preview-records-mobile {
  display: none;
}

@media (max-width: 640px) {
  .preview-table-desktop {
    display: none;
  }

  .preview-records-mobile {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .mobile-record-card {
    background-color: var(--color-surface);
    border-top: 1px solid var(--color-border);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }

  .mobile-record-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--color-border-subtle);
    padding-bottom: 0.375rem;
    margin-bottom: 0.375rem;
  }

  .record-line {
    font-size: 0.75rem;
    color: var(--color-muted);
  }

  .record-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 0.2rem 0;
  }

  .record-label {
    color: var(--color-muted);
    font-size: 0.8125rem;
  }

  .record-val {
    font-size: 0.8125rem;
    text-align: right;
  }

  .confirm-bar {
    flex-direction: column-reverse;
    align-items: stretch;
    gap: 0.75rem;
  }

  .confirm-bar .btn {
    width: 100%;
    text-align: center;
  }
}
</style>
