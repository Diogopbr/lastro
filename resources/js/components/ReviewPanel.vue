<script setup lang="ts">
import { ref, computed } from 'vue';
import type { ResultProjection } from '../core/contracts';
import { apiFetch } from '../core/api';
import { formatDateTime } from '../core/formatters';

const props = withDefaults(
  defineProps<{
    result: ResultProjection;
    expectedVersion?: number;
    hasNextPending?: boolean;
    onSave?: (note: string, action: 'reviewed' | 'reopened', next?: boolean) => Promise<void>;
    onReload?: () => Promise<void>;
  }>(),
  {
    expectedVersion: undefined,
    hasNextPending: false,
    onSave: undefined,
    onReload: undefined,
  }
);

const effectiveVersion = computed(() => props.expectedVersion ?? props.result.version);

const emit = defineEmits<{
  (e: 'saved', result: ResultProjection): void;
  (e: 'saveAndNext', result: ResultProjection): void;
  (e: 'reloadRequested'): void;
}>();

const note = ref('');
const isSubmitting = ref(false);
const conflictError = ref<string | null>(null);
const generalError = ref<string | null>(null);

async function submitReview(andNext = false) {
  isSubmitting.value = true;
  conflictError.value = null;
  generalError.value = null;

  const targetAction: 'reviewed' | 'reopened' =
    props.result.reviewStatus === 'reviewed' ? 'reopened' : 'reviewed';

  try {
    if (props.onSave) {
      await props.onSave(note.value, targetAction, andNext);
    } else {
      const updated = await apiFetch<ResultProjection>(`/resultados/${props.result.id}/revisoes`, {
        method: 'POST',
        data: {
          expectedVersion: effectiveVersion.value,
          action: targetAction,
          note: note.value,
        },
      });

      if (andNext) {
        emit('saveAndNext', updated);
      } else {
        emit('saved', updated);
      }
    }

    // Limpa a nota apenas após sucesso confirmado no servidor
    note.value = '';
  } catch (err: unknown) {
    const errorObj = err as { status?: number; code?: string; message?: string };
    if (errorObj?.status === 409 || errorObj?.code === 'conflict') {
      const msg = errorObj.message || 'O registro foi modificado por outra sessão.';
      conflictError.value = `conflito de versão detectado: ${msg}`;
    } else {
      generalError.value = errorObj?.message || 'Erro ao registrar revisão.';
    }
  } finally {
    isSubmitting.value = false;
  }
}

async function handleReload() {
  conflictError.value = null;
  if (props.onReload) {
    await props.onReload();
  }
  emit('reloadRequested');
}
</script>

<template>
  <div class="review-panel">
    <div class="panel-section">
      <h3 class="section-title">Revisão e apontamento</h3>
      <p class="section-desc">
        Registre uma justificativa ou análise técnica da divergência. O status de revisão audita a
        análise sem alterar os valores nem a classificação original do sistema.
      </p>

      <!-- Banner de Conflito 409: preserva o texto digitado -->
      <div v-if="conflictError" class="conflict-alert" data-test="conflict-banner" role="alert">
        <div class="alert-content">
          <strong>Atenção:</strong> {{ conflictError }}
        </div>
        <button
          type="button"
          class="btn btn-secondary btn-sm"
          data-test="btn-reload-version"
          @click="handleReload"
        >
          Atualizar versão dos dados
        </button>
      </div>

      <!-- Erro genérico -->
      <div v-if="generalError" class="form-error" role="alert">
        {{ generalError }}
      </div>

      <!-- Formulário de nota -->
      <div class="note-form">
        <label for="review-note-input" class="form-label">
          Nota da análise
        </label>
        <textarea
          id="review-note-input"
          v-model="note"
          data-test="review-note-input"
          class="input-textarea"
          rows="3"
          placeholder="Descreva o motivo da divergência ou conclusão da conferência…"
          :disabled="isSubmitting"
        ></textarea>

        <div class="actions-row">
          <button
            type="button"
            class="btn btn-primary"
            data-test="btn-save-review"
            :disabled="isSubmitting"
            @click="submitReview(false)"
          >
            {{ isSubmitting ? 'Salvando…' : result.reviewStatus === 'reviewed' ? 'Reabrir apontamento' : 'Salvar revisão' }}
          </button>

          <button
            v-if="hasNextPending"
            type="button"
            class="btn btn-secondary"
            data-test="btn-save-and-next"
            :disabled="isSubmitting"
            @click="submitReview(true)"
          >
            Salvar e ir para próxima
          </button>
        </div>
      </div>
    </div>

    <!-- Histórico de Auditoria -->
    <div class="panel-section history-section">
      <h4 class="history-title">Histórico de alterações (auditoria)</h4>
      <div v-if="!result.reviewEvents || result.reviewEvents.length === 0" class="history-empty">
        Nenhum apontamento registrado anteriormente.
      </div>
      <ol v-else class="history-list">
        <li
          v-for="event in result.reviewEvents"
          :key="event.id"
          class="history-item"
        >
          <div class="event-meta">
            <span class="event-actor font-semibold">{{ event.actorName }}</span>
            <span class="event-action badge" :class="event.action === 'reviewed' ? 'badge-neutral' : 'badge-pending'">
              {{ event.action === 'reviewed' ? 'Marcado como revisado' : 'Reaberto' }}
            </span>
            <span class="event-time tabular-nums">{{ formatDateTime(event.createdAt) }}</span>
          </div>
          <p v-if="event.note" class="event-note">{{ event.note }}</p>
        </li>
      </ol>
    </div>
  </div>
</template>

<style scoped>
.review-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
}

.panel-section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.section-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-ink);
  margin: 0;
}

.section-desc {
  font-size: 0.8125rem;
  color: var(--color-muted);
  margin: 0;
}

.conflict-alert {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background-color: var(--color-copper-subtle);
  border: 1px solid var(--color-copper-border);
  border-radius: var(--radius);
  color: var(--color-copper);
  font-size: 0.8125rem;
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.75rem;
  align-self: flex-start;
}

.note-form {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.actions-row {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 0.25rem;
}

.history-section {
  border-top: 1px solid var(--color-border-subtle);
  padding-top: 1rem;
}

.history-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0 0 0.5rem 0;
}

.history-empty {
  font-size: 0.8125rem;
  color: var(--color-muted);
}

.history-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.history-item {
  border-left: 2px solid var(--color-border);
  padding-left: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.event-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  font-size: 0.75rem;
  color: var(--color-muted);
}

.event-actor {
  color: var(--color-ink);
}

.event-note {
  font-size: 0.8125rem;
  color: var(--color-ink);
  margin: 0;
  background-color: var(--color-page);
  padding: 0.375rem 0.625rem;
  border-radius: var(--radius);
}
</style>
