/**
 * Virtual Cron Ping / Queue Trigger
 * Silently triggers queue ticks to process background notifications and jobs.
 */
export async function triggerQueueTick(): Promise<void> {
  const lastTick = parseInt(localStorage.getItem('nm_last_queue_tick') || '0', 10);
  const now = Date.now();

  // 2 dakikada en fazla 1 kez tetikle (120 saniye)
  if (now - lastTick < 120000) {
    return;
  }

  localStorage.setItem('nm_last_queue_tick', now.toString());

  try {
    await fetch('/api/v1/queue/tick', {
      method: 'POST',
      keepalive: true, // Sekme kapansa bile arka planda tamamlanır
    });
  } catch {
    // Sessizce yut
  }
}
