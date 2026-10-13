<script setup lang="ts">
import { ref, computed } from 'vue';
import type { SourceKind } from '../core/contracts';
import { formatFileSize } from '../core/formatters';

const props = withDefaults(
  defineProps<{
    kind: SourceKind;
    modelValue?: File | null;
    disabled?: boolean;
    error?: string;
  }>(),
  {
    modelValue: null,
    disabled: false,
    error: '',
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', file: File | null): void;
  (e: 'change', file: File | null): void;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const inputId = computed(() => `file-input-${props.kind}`);
const kindLabel = computed(() => (props.kind === 'orders' ? 'pedidos' : 'repasses'));
const templateLink = computed(() => `/modelos/${props.kind}.csv`);
const exampleLink = computed(() => `/exemplos/${props.kind}.csv`);

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0] || null;
  emit('update:modelValue', file);
  emit('change', file);
}

function handleDrop(event: DragEvent) {
  if (props.disabled) return;
  isDragging.value = false;
  const file = event.dataTransfer?.files?.[0] || null;
  if (file) {
    emit('update:modelValue', file);
    emit('change', file);
  }
}

function handleDragOver() {
  if (!props.disabled) isDragging.value = true;
}

function handleDragLeave() {
  isDragging.value = false;
}

function clearFile() {
  if (fileInput.value) {
    fileInput.value.value = '';
  }
  emit('update:modelValue', null);
  emit('change', null);
}
</script>

<template>
  <div class="file-picker">
    <!-- Dropzone não-interativa (sem role="button" nem tabindex="0") para evitar aninhamento interativo -->
    <div
      class="dropzone"
      :class="{ 'is-dragging': isDragging, 'is-disabled': disabled, 'has-file': !!modelValue }"
      @dragover.prevent="handleDragOver"
      @dragleave.prevent="handleDragLeave"
      @drop.prevent="handleDrop"
    >
      <!-- Input nativo acessível com label correspondente -->
      <input
        :id="inputId"
        ref="fileInput"
        type="file"
        accept=".csv,text/csv"
        class="sr-only"
        :disabled="disabled"
        @change="handleFileChange"
      />

      <label v-if="modelValue" :for="inputId" class="sr-only">Arquivo CSV de {{ kindLabel }}</label>

      <div v-if="!modelValue" class="dropzone-empty">
        <label
          :for="inputId"
          class="file-picker-label"
          :class="{ 'is-disabled': disabled }"
        >
          <span class="btn btn-secondary btn-select-file">
            Selecionar arquivo CSV de {{ kindLabel }}
          </span>
          <span class="dropzone-prompt">
            ou arraste o arquivo aqui
          </span>
        </label>

        <span class="dropzone-limits">
          Formato UTF-8 com vírgula, até 100 registros e 1 MB (1.048.576 bytes). Valores monetários com ponto decimal no CSV (ex.: <code>150.00</code>).
        </span>
      </div>

      <div v-else class="dropzone-file-info">
        <div class="file-meta">
          <span class="file-name font-bold">{{ modelValue.name }}</span>
          <span class="file-size tabular-nums">{{ formatFileSize(modelValue.size) }}</span>
        </div>
        <button
          type="button"
          class="btn btn-secondary btn-sm"
          data-test="btn-change-file"
          :disabled="disabled"
          @click="clearFile"
          aria-label="Trocar arquivo selecionado"
        >
          Trocar arquivo
        </button>
      </div>
    </div>

    <div v-if="error" class="form-error" role="alert">
      {{ error }}
    </div>

    <!-- Links de modelos e dados de exemplo -->
    <div class="picker-links">
      <span class="links-title">Arquivos para conferência:</span>
      <a :href="templateLink" class="model-link" download>
        Modelo vazio (.csv)
      </a>
      <span class="link-separator">·</span>
      <a :href="exampleLink" class="model-link" download>
        Exemplo fictício de maio/2026 (.csv)
      </a>
    </div>
  </div>
</template>

<style scoped>
.file-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: 100%;
}

.dropzone {
  border: 1px dashed var(--color-border);
  background-color: var(--color-surface);
  border-radius: var(--radius);
  padding: 1.25rem 1rem;
  text-align: center;
  transition: border-color 150ms ease, background-color 150ms ease;
  width: 100%;
}

.dropzone:focus-within {
  outline: 2px solid var(--color-focus);
  outline-offset: 2px;
}

.dropzone.is-dragging {
  border-color: var(--color-copper);
  background-color: var(--color-copper-subtle);
}

.dropzone.is-disabled {
  opacity: 0.6;
  background-color: var(--color-page);
}

.dropzone.has-file {
  border-style: solid;
  border-color: var(--color-border);
  background-color: var(--color-surface);
  text-align: left;
}

.dropzone-empty {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: center;
}

.file-picker-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  cursor: pointer;
}

.file-picker-label.is-disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-select-file {
  pointer-events: none; /* Deixa o clique passar para o label nativo */
}

.dropzone-prompt {
  font-size: 0.8125rem;
  color: var(--color-muted);
}

.dropzone-limits {
  font-size: 0.75rem;
  color: var(--color-muted);
  max-width: 32rem;
  line-height: 1.4;
}

.dropzone-limits code {
  background-color: var(--color-page);
  padding: 0.125rem 0.25rem;
  border-radius: 2px;
  font-size: 0.75rem;
}

.dropzone-file-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.file-meta {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  overflow: hidden;
  min-width: 0;
}

.file-name {
  font-size: 0.875rem;
  color: var(--color-ink);
  word-break: break-all;
}

.file-size {
  font-size: 0.75rem;
  color: var(--color-muted);
}

.picker-links {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--color-muted);
  line-height: 1.4;
}

.model-link {
  color: var(--color-ink);
  text-decoration: underline;
  text-underline-offset: 2px;
}

.model-link:hover {
  color: var(--color-copper);
}

.link-separator {
  color: var(--color-border);
}

@media (max-width: 640px) {
  .dropzone {
    padding: 1rem 0.75rem;
  }
  .dropzone-file-info {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
