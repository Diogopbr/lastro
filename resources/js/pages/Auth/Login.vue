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
  <AppLayout title="Entrada" is-entry-page>
    <div class="entry-view">
      <!-- Modo Demonstração: Composição Editorial -->
      <template v-if="isDemoEnabled">
        <div class="demo-editorial-grid">
          <!-- Coluna 1: Proposta, Ação e Regras -->
          <section class="demo-narrative">
            <h1 class="brand-title editorial-headline">
              Pedidos e repasses, conferidos linha a linha.
            </h1>
            <p class="editorial-lede">
              O Lastro cruza pedidos faturados com o extrato de repasses do intermediador
              financeiro. Compara o valor bruto de cada pedido, calcula diferenças exatas e
              exibe as tarifas e o valor líquido informados.
            </p>

            <div v-if="demoError" class="form-error" role="alert">
              {{ demoError }}
            </div>

            <div class="demo-action-area">
              <button
                type="button"
                class="btn btn-primary btn-demo-action"
                data-test="btn-start-demo-login"
                :disabled="isStartingDemo"
                @click="handleStartDemo"
              >
                {{ isStartingDemo ? 'Iniciando ambiente…' : 'Iniciar demonstração privada' }}
              </button>
              <p class="demo-rules-note">
                Ambiente privado com 3 exemplos de conferência, até 5 conferências no total e validade fixa de 24h por visitante.
              </p>
            </div>
          </section>

          <!-- Coluna 2: Comparação Concreta de Exemplo (Fictícia e Útil) -->
          <section class="demo-specimen" data-test="demo-concrete-example">
            <header class="specimen-header">
              <span class="specimen-tag">Exemplo fictício de conciliação</span>
              <span class="badge badge-mismatch">Divergência de valor</span>
            </header>

            <div class="specimen-body">
              <div class="specimen-row">
                <span class="row-label">Identificador do pedido</span>
                <span class="row-value tabular-nums font-semibold">0002</span>
              </div>
              <div class="specimen-row">
                <span class="row-label">Valor esperado (pedidos)</span>
                <span class="row-value tabular-nums">R$ 200,00</span>
              </div>
              <div class="specimen-row">
                <span class="row-label">Bruto informado (repasses)</span>
                <span class="row-value tabular-nums">R$ 190,00</span>
              </div>
              <div class="specimen-row highlight-diff">
                <span class="row-label">Diferença apurada</span>
                <span class="row-value tabular-nums font-semibold text-copper">-R$ 10,00</span>
              </div>
              <div class="specimen-row">
                <span class="row-label">Tarifa do intermediador</span>
                <span class="row-value tabular-nums text-muted">R$ 5,00</span>
              </div>
              <div class="specimen-row">
                <span class="row-label">Líquido informado</span>
                <span class="row-value tabular-nums font-semibold">R$ 185,00</span>
              </div>
            </div>

            <footer class="specimen-footer">
              <span class="origin-note tabular-nums">
                Origem: Linha 3 de <code>pedidos-exemplo.csv</code> · Linha 3 de <code>repasses-exemplo.csv</code>
              </span>
            </footer>
          </section>
        </div>
      </template>

      <!-- Modo Normal: Formulário Corporativo (Apenas quando demoEnabled é false) -->
      <template v-else>
        <div class="team-login-wrap">
          <header class="team-header">
            <h1 class="brand-title team-title">Lastro</h1>
            <p class="team-subtitle">
              Acesso à instalação corporativa de conferência de pedidos e repasses.
            </p>
          </header>

          <div v-if="generalError" class="form-error mb-3" role="alert">
            {{ generalError }}
          </div>

          <form @submit.prevent="handleLoginSubmit" class="team-form">
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
              class="btn btn-primary w-full"
              :disabled="isLoggingIn"
            >
              {{ isLoggingIn ? 'Entrando…' : 'Acessar instalação' }}
            </button>
          </form>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<style scoped>
.entry-view {
  width: 100%;
  max-width: 100%;
  padding: 1.5rem 0 3rem 0;
}

/* Grade Editorial de Demonstração */
.demo-editorial-grid {
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
  gap: 2.5rem;
  align-items: start;
}

.demo-narrative {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.editorial-headline {
  text-wrap: balance;
  font-size: 2.25rem;
  line-height: 1.2;
  color: var(--color-ink);
  margin: 0;
  letter-spacing: -0.02em;
}

.editorial-lede {
  font-size: 1.0625rem;
  line-height: 1.55;
  color: var(--color-muted);
  margin: 0;
}

.demo-action-area {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.btn-demo-action {
  padding: 0.75rem 1.25rem;
  font-size: 1rem;
  align-self: flex-start;
}

.demo-rules-note {
  font-size: 0.8125rem;
  color: var(--color-muted);
  margin: 0;
  line-height: 1.4;
}

/* Espécime de Exemplo Editorial */
.demo-specimen {
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.specimen-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--color-border-subtle);
  padding-bottom: 0.625rem;
}

.specimen-tag {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.specimen-body {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.specimen-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
  font-size: 0.8125rem;
  padding-bottom: 0.375rem;
  border-bottom: 1px solid var(--color-border-subtle);
}

.row-label {
  color: var(--color-muted);
}

.row-value {
  color: var(--color-ink);
  text-align: right;
}

.highlight-diff {
  background-color: var(--color-copper-subtle);
  padding: 0.375rem 0.5rem;
  border-radius: 2px;
  border-bottom: none;
}

.text-copper {
  color: var(--color-copper);
}

.text-muted {
  color: var(--color-muted);
}

.specimen-footer {
  font-size: 0.6875rem;
  color: var(--color-muted);
  padding-top: 0.25rem;
}

.origin-note code {
  background-color: var(--color-page);
  padding: 0.125rem 0.25rem;
  border-radius: 2px;
  font-size: 0.6875rem;
}

/* Modo Normal: Login de Equipe */
.team-login-wrap {
  max-width: 24rem;
  margin: 2rem auto;
  background-color: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 2rem;
}

.team-header {
  text-align: center;
  margin-bottom: 1.5rem;
}

.team-title {
  font-size: 1.75rem;
  margin-bottom: 0.25rem;
}

.team-subtitle {
  font-size: 0.8125rem;
  color: var(--color-muted);
  margin: 0;
}

.team-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.w-full {
  width: 100%;
}

.mb-3 {
  margin-bottom: 0.75rem;
}

.font-semibold {
  font-weight: 600;
}

/* Responsividade Mobile 390px */
@media (max-width: 640px) {
  .demo-editorial-grid {
    grid-template-columns: 1fr;
    gap: 1.75rem;
  }
  .editorial-headline {
    font-size: 1.625rem;
  }
  .btn-demo-action {
    width: 100%;
  }
}
</style>
