import { describe, it, expect } from 'vitest';
import { formatMoney, formatDate, formatDateTime, formatFileSize } from '../../resources/js/core/formatters';

describe('formatters', () => {
  describe('formatMoney', () => {
    it('ausência retorna traço e nunca R$ 0,00', () => {
      expect(formatMoney(null)).toBe('—');
      expect(formatMoney(undefined)).toBe('—');
      expect(formatMoney('')).toBe('—');
      expect(formatMoney('   ')).toBe('—');
    });

    it('formata valor positivo no padrão BRL', () => {
      expect(formatMoney('150.00')).toBe('R$ 150,00');
      expect(formatMoney('1234567.89')).toBe('R$ 1.234.567,89');
      expect(formatMoney('0.00')).toBe('R$ 0,00');
    });

    it('formata valor negativo no padrão BRL mantendo o sinal', () => {
      expect(formatMoney('-25.50')).toBe('-R$ 25,50');
      expect(formatMoney('-1000.00')).toBe('-R$ 1.000,00');
    });
  });

  describe('formatDate', () => {
    it('formata data YYYY-MM-DD para DD/MM/YYYY', () => {
      expect(formatDate('2026-10-10')).toBe('10/10/2026');
      expect(formatDate(null)).toBe('—');
    });
  });

  describe('formatDateTime', () => {
    it('formata timestamp ISO UTC legível', () => {
      expect(formatDateTime('2026-10-10T15:30:00Z')).toBe('10/10/2026 15:30 UTC');
      expect(formatDateTime(null)).toBe('—');
    });
  });

  describe('formatFileSize', () => {
    it('formata bytes em KB e MB', () => {
      expect(formatFileSize(500)).toBe('500 B');
      expect(formatFileSize(2048)).toBe('2.0 KB');
      expect(formatFileSize(1048576)).toBe('1.00 MB');
    });
  });
});
