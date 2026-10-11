<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { apiFetch } from '../core/api';
import { formatDateTime } from '../core/formatters';

const props = withDefaults(
  defineProps<{
    workspace: { id: string; kind: 'normal' | 'demo'; expiresAt: string | null } | null;
    demoEnabled?: boolean;
  }>(),
  {
    workspace: null,
    demoEnabled: true,
  }
);

const isResetModalOpen = ref(false);
const isResetting = ref(false);
const isStarting = ref(false);
const errorMessage = ref<string | null>(null);

const triggerBtnRef = ref<HTMLButtonElement | null>(null);
const modalRef = ref<HTMLDivElement | null>(null);
const cancelBtnRef = ref<HTMLButtonElement | null>(null);
const confirmBtnRef = ref<HTMLButtonElement | null>(null);

function openResetModal() {
  isResetModalOpen.value = true;
  errorMessage.value = null;
  nextTick(() => {
    // Foca o botão de cancelamento ou primeiro botão do modal
    cancelBtnRef.value?.focus();
  });
}

function closeResetModal() {
  isResetModalOpen.value = false;
  nextTick(() => {
    // Restaura o foco para o elemento de acionamento
    triggerBtnRef.value?.focus();
  });
}

async function confirmReset() {
  isResetting.value = true;
  errorMessage.value = null;
  try {
    await apiFetch('/demo/reset', { method: 'POST' });
    window.location.href = '/conferencias';
  } catch (err: unknown) {
    const errorObj = err as { message?: string };
    errorMessage.value = errorObj?.message || 'Falha ao reiniciar o ambiente de demonstração.';
    isResetting.value = false;
  }
}

async function startDemo() {
  isStarting.value = true;
  errorMessage.value = null;
  try {
    await apiFetch('/demo/start', { method: 'POST' });
    window.location.href = '/conferencias';
  } catch (err: unknown) {
    const errorObj = err as { message?: string };
    errorMessage.value = errorObj?.message || 'Falha ao iniciar o ambiente de demonstração.';
    isStarting.value = false;
  }
}

function handleKeyDown(event: KeyboardEvent) {
  if (!isResetModalOpen.value) return;

  if (event.key === 'Escape') {
    event.preventDefault();
    closeResetModal();
    return;
  }

  if (event.key === 'Tab') {
    // Focus trap simples entre os elementos focáveis do modal
    const focusable = modalRef.value?.querySelectorAll<HTMLElement>(
      'button:not([disabled]), [tabindex="0"]'
    );
    if (!focusable || focusable.length === 0) return;

    const firstEl = focusable[0];
    const lastEl = focusable[focusable.length - 1];

    if (event.shiftKey) {
      if (document.activeElement === firstEl) {
        event.preventDefault();
        lastEl.focus();
      }
    } else {
      if (document.activeElement === lastEl) {
        event.preventDefault();
        firstEl.focus();
      }
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
  <div class="demo-controls">
    <!-- Modo Demo Ativo -->
    <template v-if="workspace?.kind === 'demo'">
      <div class="demo-status">
        <span class="demo-pill">Demonstração</span>
        <span v-if="workspace.expiresAt" class="demo-expiry tabular-nums">
          Válido até {{ formatDateTime(workspace.expiresAt) }}
        </span>
      </div>

      <button
        ref="triggerBtnRef"
        type="button"
        class="btn btn-quiet btn-reset"
        data-test="btn-open-reset-demo"
        @click="openResetModal"
      >
        Reiniciar dados
      </button>

      <!-- Modal de Confirmação com Trap de Foco e Escape -->
      <div
        v-if="isResetModalOpen"
        class="modal-backdrop"
        @click.self="closeResetModal"
      >
        <div
          ref="modalRef"
          class="modal-card"
          data-test="reset-demo-modal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="modal-reset-title"
        >
          <h3 id="modal-reset-title" class="modal-title">
            Reiniciar ambiente de demonstração?
          </h3>
          <p class="modal-body">
            Todas as conferências, fontes e revisões atuais serão descartadas e substituídas pelo
            conjunto de dados de demonstração inicial (3 conferências de exemplo).
          </p>

          <div v-if="errorMessage" class="form-error" role="alert">
            {{ errorMessage }}
          </div>

          <div class="modal-actions">
            <button
              ref="cancelBtnRef"
              type="button"
              class="btn btn-secondary"
              data-test="btn-cancel-reset"
              :disabled="isResetting"
              @click="closeResetModal"
            >
              Cancelar
            </button>
            <button
              ref="confirmBtnRef"
              type="button"
              class="btn btn-danger"
              data-test="btn-confirm-reset"
              :disabled="isResetting"
              @click="confirmReset"
            >
              {{ isResetting ? 'Reiniciando…' : 'Sim, reiniciar dados' }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Sem workspace e Demo disponível -->
    <template v-else-if="!workspace && demoEnabled">
      <button
        type="button"
        class="btn btn-secondary btn-start-demo"
        data-test="btn-start-demo"
        :disabled="isStarting"
        @click="startDemo"
      >
        {{ isStarting ? 'Iniciando…' : 'Testar demonstração privada' }}
      </button>
    </template>
  </div>
</template>

<style scoped>
.demo-controls {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.demo-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.demo-pill {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-copper);
  background-color: var(--color-copper-subtle);
  border: 1px solid var(--color-copper-border);
  padding: 0.125rem 0.375rem;
  border-radius: var(--radius);
}

.demo-expiry {
  font-size: 0.75rem;
  color: var(--color-muted);
}

.btn-reset {
  font-size: 0.8125rem;
  color: var(--color-ink);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(24, 50, 74, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-card {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  max-width: 28rem;
  width: 100%;
  padding: 1.5rem;
  box-shadow: 0 4px 12px rgba(24, 50, 74, 0.08);
}

.modal-title {
  font-size: 1.0625rem;
  margin: 0 0 0.5rem 0;
  color: var(--color-ink);
}

.modal-body {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0 0 1.25rem 0;
  line-height: 1.4;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}
</style>
