<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '../core/contracts';
import DemoControls from '../components/DemoControls.vue';
import { apiFetch } from '../core/api';

defineProps<{
  title?: string;
  breadcrumbs?: Array<{ label: string; href?: string }>;
}>();

const page = usePage<SharedProps>();
const auth = computed(() => {
  try {
    return page?.props?.auth || null;
  } catch {
    return null;
  }
});
const flash = computed(() => {
  try {
    return page?.props?.flash || null;
  } catch {
    return null;
  }
});
const currentUrl = computed(() => {
  try {
    return page?.url || '';
  } catch {
    return '';
  }
});

const isLoggingOut = ref(false);

async function handleLogout() {
  if (isLoggingOut.value) return;
  isLoggingOut.value = true;
  try {
    const res = await apiFetch<{ redirect?: string }>('/logout', { method: 'POST' });
    window.location.href = res?.redirect || '/entrar';
  } catch {
    window.location.href = '/entrar';
  } finally {
    isLoggingOut.value = false;
  }
}
</script>

<template>
  <div class="app-shell">
    <Head :title="title ? `${title} — Lastro` : 'Lastro'" />

    <!-- Acessibilidade: Pular para conteúdo principal -->
    <a href="#main-content" class="sr-only focus-skip">Pular para o conteúdo principal</a>

    <!-- Barra de Navegação Superior (Editorial) -->
    <header class="top-nav">
      <div class="nav-container">
        <div class="nav-left">
          <Link href="/conferencias" class="brand-link">
            <span class="brand-name brand-title">Lastro</span>
            <span class="brand-tagline">Conferência de pedidos e repasses</span>
          </Link>

          <nav class="nav-links" aria-label="Navegação principal">
            <Link
              href="/conferencias"
              class="nav-link"
              :class="{ 'is-active': currentUrl.startsWith('/conferencias') }"
            >
              Conferências
            </Link>
          </nav>
        </div>

        <div class="nav-right">
          <!-- Controles de Demonstração -->
          <DemoControls
            :workspace="auth?.workspace || null"
            :demo-enabled="auth?.demoEnabled ?? true"
          />

          <!-- Informações de Sessão / Acesso de Equipe -->
          <div class="user-access">
            <template v-if="auth?.user">
              <span class="user-name">{{ auth.user.displayName }}</span>
              <button
                type="button"
                class="btn btn-quiet btn-logout"
                :disabled="isLoggingOut"
                @click="handleLogout"
              >
                {{ isLoggingOut ? 'Saindo…' : 'Sair' }}
              </button>
            </template>
            <template v-else-if="!auth?.workspace">
              <Link href="/entrar" class="btn btn-quiet btn-login">
                Entrar
              </Link>
            </template>
          </div>
        </div>
      </div>

      <!-- Barra de Migalhas (Breadcrumbs) se fornecidas -->
      <div v-if="breadcrumbs && breadcrumbs.length > 0" class="breadcrumbs-bar">
        <div class="breadcrumbs-container">
          <nav aria-label="Trilha de navegação">
            <ol class="breadcrumbs-list">
              <li v-for="(crumb, idx) in breadcrumbs" :key="idx" class="breadcrumb-item">
                <Link v-if="crumb.href" :href="crumb.href" class="breadcrumb-link">
                  {{ crumb.label }}
                </Link>
                <span v-else class="breadcrumb-current" aria-current="page">
                  {{ crumb.label }}
                </span>
                <span v-if="idx < breadcrumbs.length - 1" class="breadcrumb-sep">/</span>
              </li>
            </ol>
          </nav>
        </div>
      </div>
    </header>

    <!-- Alerta Flash de Sucesso -->
    <div v-if="flash?.success" class="flash-banner" role="status">
      <div class="flash-container">
        <span>{{ flash.success }}</span>
      </div>
    </div>

    <!-- Conteúdo Principal -->
    <main id="main-content" class="main-viewport">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--color-page);
  color: var(--color-ink);
}

.focus-skip:focus {
  position: static;
  width: auto;
  height: auto;
  padding: 0.5rem 1rem;
  background-color: var(--color-ink);
  color: #ffffff;
  z-index: 9999;
}

.top-nav {
  background-color: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  position: sticky;
  top: 0;
  z-index: 50;
}

.nav-container {
  max-width: 76rem;
  margin: 0 auto;
  padding: 0.75rem 1.25rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.nav-left {
  display: flex;
  align-items: center;
  gap: 2rem;
}

.brand-link {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  text-decoration: none;
  color: inherit;
}

.brand-name {
  font-size: 1.375rem;
  color: var(--color-ink);
}

.brand-tagline {
  font-size: 0.75rem;
  color: var(--color-muted);
  display: none;
}

@media (min-width: 768px) {
  .brand-tagline {
    display: inline;
  }
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.nav-link {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--color-muted);
  text-decoration: none;
  padding: 0.25rem 0.5rem;
  border-radius: var(--radius);
  transition: color 150ms ease;
}

.nav-link:hover, .nav-link.is-active {
  color: var(--color-ink);
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-access {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  border-left: 1px solid var(--color-border);
  padding-left: 0.75rem;
}

.user-name {
  color: var(--color-ink);
  font-weight: 600;
}

.btn-logout, .btn-login {
  font-size: 0.8125rem;
  padding: 0.25rem 0.5rem;
}

.breadcrumbs-bar {
  background-color: var(--color-page);
  border-top: 1px solid var(--color-border-subtle);
  padding: 0.375rem 1.25rem;
}

.breadcrumbs-container {
  max-width: 76rem;
  margin: 0 auto;
}

.breadcrumbs-list {
  display: flex;
  align-items: center;
  list-style: none;
  margin: 0;
  padding: 0;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--color-muted);
}

.breadcrumb-link {
  color: var(--color-muted);
  text-decoration: none;
}

.breadcrumb-link:hover {
  color: var(--color-ink);
  text-decoration: underline;
}

.breadcrumb-current {
  color: var(--color-ink);
  font-weight: 600;
}

.breadcrumb-sep {
  color: var(--color-border);
}

.flash-banner {
  background-color: var(--color-match-subtle);
  color: var(--color-match);
  border-bottom: 1px solid var(--color-match-border);
  font-size: 0.875rem;
  padding: 0.625rem 1.25rem;
}

.flash-container {
  max-width: 76rem;
  margin: 0 auto;
}

.main-viewport {
  flex: 1;
  max-width: 76rem;
  width: 100%;
  margin: 0 auto;
  padding: 1.5rem 1.25rem 3rem 1.25rem;
}

@media (max-width: 640px) {
  .nav-container {
    padding: 0.625rem 1rem;
    gap: 0.75rem;
  }
  .main-viewport {
    padding: 1rem 1rem 2rem 1rem;
  }
}
</style>
