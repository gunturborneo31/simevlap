export function formatRupiah(val) {
  if (val === undefined || val === null) return '0';
  return val.toLocaleString('id-ID');
}

// Format angka gaya Indonesia: 1.234.567 atau 1.234,56 (maks. `digits` desimal).
export function formatAngka(val, digits = 2) {
  if (val === undefined || val === null || val === '') return '-';
  const num = Number(val);
  if (Number.isNaN(num)) return String(val);
  return num.toLocaleString('id-ID', { maximumFractionDigits: digits });
}

export function formatTanggal(date) {
  if (!date) return '';
  try {
    return new Date(date).toLocaleDateString('id-ID');
  } catch {
    return '';
  }
}
