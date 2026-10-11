import type { Classification, ReviewStatus } from './contracts';

/**
 * Formata um valor monetário em string decimal ("123.45" ou "-12.50").
 * Ausência (null/undefined/vazio) retorna "—", NUNCA "R$ 0,00".
 */
export function formatMoney(amount: string | null | undefined): string {
  if (amount === null || amount === undefined || amount.trim() === '') {
    return '—';
  }

  const isNegative = amount.startsWith('-');
  const cleanAmount = isNegative ? amount.slice(1) : amount;
  const [integerPart, decimalPart = '00'] = cleanAmount.split('.');

  // Formatação de milhar com ponto e decimais com vírgula (padrão BRL)
  const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const formattedDecimal = decimalPart.padEnd(2, '0').slice(0, 2);

  const formatted = `R$ ${formattedInteger},${formattedDecimal}`;
  return isNegative ? `-${formatted}` : formatted;
}

/**
 * Formata data no padrão YYYY-MM-DD para DD/MM/YYYY.
 */
export function formatDate(date: string | null | undefined): string {
  if (!date) return '—';
  const parts = date.split('-');
  if (parts.length === 3) {
    const [year, month, day] = parts;
    return `${day}/${month}/${year}`;
  }
  return date;
}

/**
 * Formata timestamp ISO em UTC para exibição legível (DD/MM/YYYY HH:mm).
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    const day = String(d.getUTCDate()).padStart(2, '0');
    const month = String(d.getUTCMonth() + 1).padStart(2, '0');
    const year = d.getUTCFullYear();
    const hours = String(d.getUTCHours()).padStart(2, '0');
    const minutes = String(d.getUTCMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes} UTC`;
  } catch {
    return iso;
  }
}

/**
 * Formata tamanho de arquivo em bytes para KB ou MB.
 */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

/**
 * Rótulo humano para a classificação de conciliação.
 */
export function classificationLabel(classification: Classification): string {
  switch (classification) {
    case 'matched':
      return 'Correspondência exata';
    case 'amountMismatch':
      return 'Divergência de valor';
    case 'missingPayout':
      return 'Repasse ausente';
    case 'missingOrder':
      return 'Pedido ausente';
  }
}

/**
 * Rótulo humano para o status de revisão.
 */
export function reviewStatusLabel(status: ReviewStatus): string {
  if (status === 'reviewed') return 'Revisada';
  if (status === 'pending') return 'Pendente';
  return '';
}
