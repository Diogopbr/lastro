<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import { apiFetch } from '../../core/api';
import type { SharedProps } from '../../core/contracts';

const props = defineProps<{
  demoEnabled?: boolean;
}>();

const page = usePage<SharedProps>();
const isDemoEnabled = computed(() => {
  return props.demoEnabled ?? page?.props?.auth?.demoEnabled ?? false;
});

const isStartingDemo = ref(false);
const demoError = ref<string | null>(null);

const email = ref('');
const password = ref('');
const isLoggingIn = ref(false);
const formErrors = ref<Record<string, string>>({});
const generalError = ref<string | null>(null);

async function handleStartDemo() {
  isStartingDemo.value = true;
  demoError.value = null;
  try {
    await apiFetch('/demo/start', { method: 'POST' });
    window.location.href = '/conferencias';
  } catch (err: unknown) {
    const errorObj = err as { message?: string };
    demoError.value = errorObj?.message || 'Não foi possível iniciar a demonstração no momento.';
    isStartingDemo.value = false;
  }
}

async function handleLoginSubmit() {
  isLoggingIn.value = true;
  formErrors.value = {};
  generalError.value = null;

  try {
    const res = await apiFetch<{ redirect?: string }>('/login', {
      method: 'POST',
      data: {
        email: email.value,
        password: password.value,
      },
    });

    window.location.href = res?.redirect || '/conferencias';
  } catch (err: unknown) {
    const errorObj = err as { message?: string; errors?: Record<string, string[] | string> };
    if (errorObj?.errors) {
      const mapped: Record<string, string> = {};
      for (const [key, val] of Object.entries(errorObj.errors)) {
        mapped[key] = Array.isArray(val) ? val[0] : String(val);
      }
      formErrors.value = mapped;
    }
    generalError.value = errorObj?.message || 'Falha ao realizar login. Verifique suas credenciais.';
  } finally {
    isLoggingIn.value = false;
  }
}
</script>

<template>
  <AppLayout title="Entrada">
    <div class="auth-page">
      <div class="auth-card">
        <header class="auth-header">
          <h1 class="brand-title auth-title">Lastro</h1>
          <p class="auth-subtitle">
            Conferência e conciliação de pedidos pagos e repasses do intermediador.
          </p>
        </header>

        <!-- Seção Demonstração Privada -->
        <section v-if="isDemoEnabled" class="demo-section">
          <div class="demo-box">
            <h2 class="section-heading">Ambiente de Demonstração</h2>
            <p class="section-desc">
              Inicie um ambiente isolado com 3 conferências de exemplo pré-carregadas para avaliar
              o fluxo de conferência, divergências e auditoria.
            </p>

            <div v-if="demoError" class="form-error mb-2" role="alert">
              {{ demoError }}
            </div>

            <button
              type="button"
              class="btn btn-primary w-full"
              data-test="btn-start-demo-login"
              :disabled="isStartingDemo"
              @click="handleStartDemo"
            >
              {{ isStartingDemo ? 'Preparando ambiente…' : 'Iniciar demonstração privada' }}
            </button>
            <span class="demo-footnote">Validade de 24 horas por visitante. Sem senha compartilhada.</span>
          </div>

          <div class="divider-text">
            <span>ou acesse com credenciais de equipe</span>
          </div>
        </section>

        <!-- Formulário de Acesso de Equipe -->
        <section class="login-section">
          <h2 class="section-heading sr-only">Credenciais de equipe</h2>

          <div v-if="generalError" class="form-error mb-2" role="alert">
            {{ generalError }}
          </div>

          <form @submit.prevent="handleLoginSubmit" class="login-form">
            <div class="form-group">
              <label for="email" class="form-label">E-mail corporativo</label>
              <input
                id="email"
                v-model="email"
                type="email"
                class="input-text"
                autocomplete="email"
                required
                :disabled="isLoggingIn"
              />
              <span v-if="formErrors.email" class="form-error" role="alert">
                {{ formErrors.email }}
              </span>
            </div>

            <div class="form-group">
              <label for="password" class="form-label">Senha</label>
              <input
                id="password"
                v-model="password"
                type="password"
                class="input-text"
                autocomplete="current-password"
                required
                :disabled="isLoggingIn"
              />
              <span v-if="formErrors.password" class="form-error" role="alert">
                {{ formErrors.password }}
              </span>
            </div>

            <button
              type="submit"
              class="btn btn-secondary w-full"
              :disabled="isLoggingIn"
            >
              {{ isLoggingIn ? 'Entrando…' : 'Acessar instalação' }}
            </button>
          </form>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.auth-page {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem 0;
}

.auth-card {
  max-width: 26rem;
  width: 100%;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 2rem;
}

.auth-header {
  text-align: center;
  margin-bottom: 1.5rem;
}

.auth-title {
  font-size: 1.875rem;
  margin-bottom: 0.375rem;
}

.auth-subtitle {
  font-size: 0.875rem;
  color: var(--color-muted);
  margin: 0;
}

.demo-section {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.demo-box {
  background-color: var(--color-page);
  border: 1px solid var(--color-border-subtle);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.section-heading {
  font-size: 0.9375rem;
  font-weight: 600;
  margin: 0;
  color: var(--color-ink);
}

.section-desc {
  font-size: 0.8125rem;
  color: var(--color-muted);
  margin: 0;
  line-height: 1.4;
}

.demo-footnote {
  font-size: 0.6875rem;
  color: var(--color-muted);
  text-align: center;
}

.divider-text {
  display: flex;
  align-items: center;
  text-align: center;
  color: var(--color-muted);
  font-size: 0.75rem;
}

.divider-text::before,
.divider-text::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid var(--color-border-subtle);
}

.divider-text span {
  padding: 0 0.5rem;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.w-full {
  width: 100%;
}

.mb-2 {
  margin-bottom: 0.5rem;
}
</style>
