export function formatRupiah(val) {
  if (val === undefined || val === null) return '0';
  return val.toLocaleString('id-ID');
}

export function formatTanggal(date) {
  if (!date) return '';
  try {
    return new Date(date).toLocaleDateString('id-ID');
  } catch {
    return '';
  }
}
