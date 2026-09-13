export function createDashboardChartManager(
  {
    documentRef = globalThis.document,
    chartConstructor = () => globalThis.window?.Chart,
    translate = (_key, fallback) => fallback,
  } = {},
) {
  let charts = [];
  const destroy = () => {
    charts.forEach((chart) => chart.destroy());
    charts = [];
  };
  const create = (id, config) => {
    const canvas = documentRef?.getElementById(id);
    const Chart = chartConstructor();
    if (!canvas || typeof Chart !== "function") return false;
    charts.push(new Chart(canvas.getContext("2d"), config));
    return true;
  };
  const setState = (
    id,
    hasData,
    message = translate("admin.dashboard.no_data", "Bu dönem için veri yok."),
  ) => {
    const canvas = documentRef?.getElementById(id);
    if (!canvas) return false;
    canvas.hidden = !hasData;
    const empty = canvas.parentElement?.querySelector("[data-chart-empty]");
    if (empty) {
      empty.hidden = hasData;
      empty.textContent = message;
    }
    return hasData;
  };
  return Object.freeze({ destroy, create, setState });
}
