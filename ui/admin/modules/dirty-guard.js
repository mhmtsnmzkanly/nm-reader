/**
 * Unsaved form changes guard preventing accidental navigation or tab closure.
 */

export function createDirtyGuard({
  windowRef = globalThis.window,
  confirmAction = () => true,
  translate = (_key, fallback) => fallback,
} = {}) {
  let activeForm = null;
  let isFormDirty = false;
  let formInitialData = null;

  function serializeForm(form) {
    if (!form) return "";
    try {
      const FormDataCtor = form.ownerDocument?.defaultView?.FormData || globalThis.FormData;
      if (typeof FormDataCtor === "function") {
        try {
          const data = new FormDataCtor(form);
          const entries = Array.from(data.entries()).sort(([a], [b]) => a.localeCompare(b));
          return JSON.stringify(entries);
        } catch {
          // fallback to scanning elements
        }
      }
      if (form.elements) {
        const entries = [];
        for (const el of form.elements) {
          if (!el.name || el.disabled) continue;
          if (el.type === "checkbox" || el.type === "radio") {
            if (el.checked) entries.push([el.name, el.value]);
          } else {
            entries.push([el.name, el.value]);
          }
        }
        entries.sort(([a], [b]) => a.localeCompare(b));
        return JSON.stringify(entries);
      }
      return "";
    } catch {
      return "";
    }
  }

  function register(form) {
    if (!form) return () => {};
    activeForm = form;
    formInitialData = serializeForm(form);
    isFormDirty = false;

    const onInput = () => {
      isFormDirty = serializeForm(form) !== formInitialData;
    };

    form.addEventListener("input", onInput);
    form.addEventListener("change", onInput);

    return () => {
      if (activeForm === form) {
        activeForm = null;
        isFormDirty = false;
        formInitialData = null;
      }
      form.removeEventListener("input", onInput);
      form.removeEventListener("change", onInput);
    };
  }

  function markClean() {
    isFormDirty = false;
    formInitialData = serializeForm(activeForm);
  }

  function isDirty() {
    return isFormDirty;
  }

  function checkCanNavigate() {
    if (!isFormDirty) return true;
    const message = translate(
      "admin.form.unsaved_changes",
      "Kaydedilmemiş değişiklikleriniz var. Sayfadan ayrılmak istediğinize emin misiniz?",
    );
    const confirmed = confirmAction(message);
    if (confirmed) {
      isFormDirty = false;
    }
    return confirmed;
  }

  const onBeforeUnload = (event) => {
    if (isFormDirty) {
      event.preventDefault();
      event.returnValue = "";
    }
  };

  windowRef?.addEventListener?.("beforeunload", onBeforeUnload);

  function cleanup() {
    activeForm = null;
    isFormDirty = false;
    windowRef?.removeEventListener?.("beforeunload", onBeforeUnload);
  }

  return Object.freeze({
    register,
    markClean,
    isDirty,
    checkCanNavigate,
    cleanup,
  });
}
