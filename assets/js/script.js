// WattWatch — client-side behavior
// Polls ajax/get-live-data.php every 10s to refresh stat cards + equipment
// power figures without a full page reload. Chart drawing (if a
// #powerChart canvas is present) is handled by dashboard.php inline,
// which already has the day's data server-rendered on load.

(function () {
  const POLL_MS = 10000;

  async function pollLiveData() {
    const root = document.querySelector('[data-live-root]');
    if (!root) return;
    try {
      const res = await fetch('ajax/get-live-data.php', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();

      const totalPowerEl = document.querySelector('[data-live="total-power"]');
      if (totalPowerEl && data.total_power_now !== undefined) {
        totalPowerEl.textContent = Number(data.total_power_now).toLocaleString() + ' W';
      }
      const anomalyCountEl = document.querySelector('[data-live="anomaly-count"]');
      if (anomalyCountEl && data.active_anomalies !== undefined) {
        anomalyCountEl.textContent = data.active_anomalies;
      }
      (data.equipment || []).forEach(function (item) {
        const powerEl = document.querySelector('[data-equip-power="' + item.equipment_id + '"]');
        if (powerEl) powerEl.textContent = Number(item.power_watts).toLocaleString() + ' W';
        const badgeEl = document.querySelector('[data-equip-badge="' + item.equipment_id + '"]');
        if (badgeEl) {
          badgeEl.className = 'badge ' + (item.is_anomaly ? 'anomaly' : 'normal');
          badgeEl.textContent = item.is_anomaly ? '● Anomaly' : '● Normal';
        }
      });
    } catch (err) {
      console.error('Live poll failed', err);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('[data-live-root]')) {
      setInterval(pollLiveData, POLL_MS);
    }
  });
})();
