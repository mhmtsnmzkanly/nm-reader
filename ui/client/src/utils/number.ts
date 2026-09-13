/**
 * Converts values received from API/SSR payloads to a finite number.
 *
 * PDO-backed JSON responses can contain numeric columns as strings depending
 * on the driver configuration. Keeping this conversion at the UI boundary
 * prevents formatting calls such as `toFixed` from crashing the render tree.
 */
export function toSafeNumber(value: unknown, fallback = 0): number {
  if (typeof value === 'string' && value.trim() === '') {
    return fallback;
  }

  const numeric = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(numeric) ? numeric : fallback;
}
