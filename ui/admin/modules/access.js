/** Frontend permission policy; backend remains authoritative. */
export function createAccess(initialPermissions = []) {
  let permissions = new Set(initialPermissions);
  const can = (permission) =>
    permissions.has("*") || permissions.has(String(permission));
  const any = (required = []) => {
    const values = Array.isArray(required) ? required : [required];
    return values.length === 0 || values.some(can);
  };
  const all = (required = []) => {
    const values = Array.isArray(required) ? required : [required];
    return values.every(can);
  };
  return Object.freeze({
    can,
    any,
    all,
    setPermissions: (next) => {
      permissions = new Set(Array.isArray(next) ? next : []);
    },
    snapshot: () => new Set(permissions),
  });
}
