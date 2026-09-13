/** Shared table targets for both data rows and loading/error states. */
export const collectionTables = Object.freeze({
  series: Object.freeze({ id: "panel-series-list", partial: "panel-rows-series", columns: 6 }),
  blogs: Object.freeze({ id: "panel-blogs-list", partial: "panel-rows-blogs", columns: 5 }),
  comments: Object.freeze({ id: "panel-comments-list", partial: "panel-rows-comments", columns: 7 }),
  reports: Object.freeze({ id: "panel-reports-list", partial: "panel-rows-reports", columns: 7 }),
  packages: Object.freeze({ id: "panel-packages-list", partial: "panel-rows-packages", columns: 6 }),
  finance: Object.freeze({ id: "panel-finance-list", partial: "panel-rows-finance", columns: 8 }),
  logs: Object.freeze({ id: "panel-audit-logs", partial: "panel-rows-logs", columns: 7 }),
  uploads: Object.freeze({ id: "panel-uploads-list", partial: "panel-rows-uploads", columns: 9 }),
  users: Object.freeze({ id: "panel-users-list", partial: "panel-rows-users", columns: 6 }),
});

export function createCollectionTableRenderer(setTableRows) {
  return function renderCollectionTable(name, items = [], state = {}) {
    if (!Object.hasOwn(collectionTables, name)) {
      throw new Error(`Unknown collection table: ${name}`);
    }
    const { id, partial, columns } = collectionTables[name];
    return setTableRows(id, partial, items, columns, state);
  };
}
