/** RBAC matrix and ownership administration pages. */
export function createAccessControlPagesController({
  api,
  getPageEpoch,
  assertCurrentPage,
  hasPermission,
  mountEditorPage,
  mountHeaderCells,
  mountPartial,
  confirmAction = () => false,
  showToast,
  registerPageCleanup,
  translate = (_key, fallback) => fallback,
} = {}) {
  async function loadRbacPage() {
    const requestEpoch = getPageEpoch();
    const response = await api("/rbac/matrix");
    assertCurrentPage(requestEpoch);
    const roles = response?.data?.roles || [];
    const permissionGroups = response?.data?.permissions || {};
    const rows = [];
    Object.entries(permissionGroups).forEach(([group, permissions]) => {
      rows.push({
        group,
        group_class: "",
        permission_class: "d-none",
        role_colspan: roles.length + 1,
        cells: [],
      });
      Object.entries(permissions).forEach(([code, label]) => {
        const cells = roles.map((role) => {
          const rolePermissions = String(role.permissions || "").split(",");
          const granted = role.slug === "superadmin" ||
            rolePermissions.includes("*") ||
            rolePermissions.includes(code);
          const canChange = granted
            ? hasPermission("admin.permissions.revoke")
            : hasPermission("admin.permissions.grant");
          const locked = role.slug === "admin" && code === "admin.panel.access";
          const interactive = canChange && !locked;
          return {
            role: role.slug,
            permission: code,
            granted_value: granted ? "1" : "0",
            interactive,
            button_class: interactive ? "" : "d-none",
            icon_class_hidden: interactive ? "d-none" : "",
            title: granted
              ? translate("admin.rbac.revoke", "İzni kaldır")
              : translate("admin.rbac.grant", "İzni ver"),
            icon_class: granted
              ? "bi-check-circle-fill text-success"
              : "bi-x-circle text-secondary",
          };
        });
        rows.push({
          code,
          label,
          group_class: "d-none",
          permission_class: "",
          cells,
        });
      });
    });
    const page = mountEditorPage(
      translate("admin.rbac.title", "Yetki ve Rol Matrisi"),
      {
        name: "panel-rbac",
        context: {
          roles: roles.map((role) => ({ name: role.name || role.slug })),
        },
      },
    );
    mountHeaderCells(
      page.querySelector("#panel-rbac-head"),
      [translate("admin.rbac.permission", "İzin"), ...roles.map((role) => role.name || role.slug)],
      "text-center",
    );
    mountPartial("panel-rows-rbac", page.querySelector("#panel-rbac-rows"), {
      rows,
      has_items: rows.length > 0,
      role_colspan: roles.length + 1,
    });
    const onClick = async (event) => {
      const button = event.target.closest("[data-toggle-permission]");
      if (!button) return;
      const granted = button.dataset.granted === "1";
      if (
        !confirmAction(
          translate(
            "admin.confirm.permission_change",
            "{permission} izni {role} rolü için {change} mi?",
            {
              permission: button.dataset.permission,
              role: button.dataset.role,
              change: granted
                ? translate("admin.rbac.remove_future", "kaldırılsın")
                : translate("admin.rbac.grant_future", "verilsin"),
            },
          ),
        )
      ) {
        return;
      }
      try {
        await api(granted ? "/rbac/permissions" : "/rbac/permissions/assign", {
          method: granted ? "DELETE" : "POST",
          body: {
            role: button.dataset.role,
            permission: button.dataset.permission,
          },
        });
        showToast(
          granted
            ? translate("admin.rbac.revoked", "İzin kaldırıldı")
            : translate("admin.rbac.granted", "İzin verildi"),
        );
        await loadRbacPage();
      } catch (error) {
        if (error?.name === "AbortError") return;
        showToast(error.message, "danger");
      }
    };
    page.addEventListener("click", onClick);
    registerPageCleanup?.(() => page.removeEventListener("click", onClick));
  }

  async function loadOwnershipPage() {
    const requestEpoch = getPageEpoch();
    const [matrixResponse, ownershipResponse] = await Promise.all([
      api("/rbac/matrix"),
      api("/rbac/ownership"),
    ]);
    assertCurrentPage(requestEpoch);
    const roles = matrixResponse?.data?.roles || [];
    const capabilities = ownershipResponse?.data?.capabilities || [];
    const records = ownershipResponse?.data?.records || [];
    const rolePermissions = (role) =>
      String(role.permissions || "")
        .split(",")
        .map((value) => value.trim())
        .filter(Boolean);
    const canRole = (role, capability) => {
      if (capability.scope === "owner") {
        return translate("admin.ownership.owner", "Sahibi");
      }
      if (capability.scope === "authenticated") {
        return translate("admin.ownership.authenticated", "Giriş yapmış kullanıcı");
      }
      const permissions = rolePermissions(role);
      if (role.slug === "superadmin" || permissions.includes("*")) {
        return translate("admin.boolean.yes", "Evet");
      }
      if (capability.scope === "role_any") {
        return String(capability.permission || "")
            .split(" veya ")
            .some((permission) => permissions.includes(permission))
          ? translate("admin.boolean.yes", "Evet")
          : translate("admin.boolean.no", "Hayır");
      }
      return permissions.includes(capability.permission)
        ? translate("admin.boolean.yes", "Evet")
        : translate("admin.boolean.no", "Hayır");
    };
    const capabilityItems = capabilities.map((capability) => ({
      entity_label: capability.entity_label || capability.entity_type || "-",
      action: capability.action || "-",
      permission: capability.permission ||
        translate("admin.ownership.record_owner", "kayıt sahibi"),
      cells: roles.map((role) => {
        const value = canRole(role, capability);
        return {
          value,
          class_name: value === "Evet"
            ? "text-success"
            : value === "Sahibi"
            ? "text-info"
            : "text-secondary",
        };
      }),
    }));
    const recordItems = records.map((record) => ({
      entity_type: record.entity_type || "-",
      label: record.label || record.entity_id || "-",
      entity_id: record.entity_id || "",
      owner: record.owner_username || record.owner_id || "-",
      created_at: record.created_at || "-",
    }));
    const page = mountEditorPage(
      translate("admin.ownership.title", "İçerik Sahipliği ve İşlem Yetkileri"),
      {
        name: "panel-ownership",
        context: {
          roles: roles.map((role) => ({ name: role.name || role.slug })),
        },
      },
    );
    mountHeaderCells(
      page.querySelector("#panel-ownership-head"),
      [
        translate("admin.ownership.entity", "Varlık"),
        translate("admin.ownership.action", "İşlem"),
        translate("admin.ownership.requirement", "Gerekli izin / kapsam"),
        ...roles.map((role) => role.name || role.slug),
      ],
      "text-center",
    );
    mountPartial(
      "panel-rows-ownership-capabilities",
      page.querySelector("#panel-ownership-capability-rows"),
      {
        items: capabilityItems,
        has_items: capabilityItems.length > 0,
        colspan: roles.length + 3,
      },
    );
    mountPartial(
      "panel-rows-ownership-records",
      page.querySelector("#panel-ownership-record-rows"),
      { items: recordItems, has_items: recordItems.length > 0 },
    );
  }

  return Object.freeze({ loadRbacPage, loadOwnershipPage });
}
