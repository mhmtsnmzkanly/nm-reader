/**
 * AdminLTE 4 / Bootstrap 5 Modal Service.
 *
 * Provides Promise-based dialogs (confirm, prompt, alert, dialog)
 * replacing native browser dialogs with AdminLTE UI components.
 */

function getBootstrapModal(element, windowRef) {
  const bs = windowRef?.bootstrap || globalThis.bootstrap;
  if (bs?.Modal) {
    return bs.Modal.getOrCreateInstance(element);
  }
  return null;
}

function showModal(element, windowRef) {
  const bsModal = getBootstrapModal(element, windowRef);
  if (bsModal) {
    bsModal.show();
  } else {
    element.classList.add("show");
    element.style.display = "block";
    element.removeAttribute("aria-hidden");
    element.setAttribute("aria-modal", "true");
  }
}

function hideModal(element, windowRef) {
  if (element && element.contains(element.ownerDocument?.activeElement)) {
    element.ownerDocument.activeElement.blur?.();
  }
  const bsModal = getBootstrapModal(element, windowRef);
  if (bsModal) {
    try {
      bsModal.hide();
    } catch {}
  } else {
    element.classList.remove("show");
    element.style.display = "none";
    element.setAttribute("aria-hidden", "true");
    element.removeAttribute("aria-modal");
    const backdrops = element.ownerDocument?.querySelectorAll?.(".modal-backdrop") || [];
    for (const b of backdrops) b.remove();
    element.dispatchEvent(new (windowRef?.Event || globalThis.Event)("hidden.bs.modal"));
  }
}

export function createModalService({
  documentRef = globalThis.document,
  windowRef = globalThis.window,
  translate = (_key, fallback) => fallback,
} = {}) {
  /**
   * Confirmation dialog returning Promise<boolean>.
   *
   * @param {string|{
   *   title?: string,
   *   message: string,
   *   confirmText?: string,
   *   cancelText?: string,
   *   variant?: "danger"|"primary"|"warning"|"success"|"secondary",
   *   icon?: string
   * }} options
   * @returns {Promise<boolean>}
   */
  function confirm(options) {
    const opts = typeof options === "string" ? { message: options } : (options || {});
    const message = String(opts.message || "");
    const title = opts.title || translate("admin.modal.confirm_title", "Onay Gerekiyor");
    const confirmText = opts.confirmText || translate("admin.modal.confirm", "Onayla");
    const cancelText = opts.cancelText || translate("admin.modal.cancel", "İptal");
    const variant = opts.variant || "danger";
    const icon = opts.icon || "bi-exclamation-triangle-fill";

    const modalEl = documentRef?.getElementById?.("panel-confirm-modal");
    if (!modalEl) {
      return Promise.resolve(
        typeof windowRef?.confirm === "function" ? windowRef.confirm(message) : true,
      );
    }

    const headingEl = modalEl.querySelector("#panel-confirm-modal-heading");
    const messageEl = modalEl.querySelector("#panel-confirm-modal-message");
    const submitBtn = modalEl.querySelector("#panel-confirm-modal-submit");
    const cancelBtn = modalEl.querySelector("#panel-confirm-modal-cancel");
    const iconEl = modalEl.querySelector("#panel-confirm-modal-icon");
    const closeBtn = modalEl.querySelector(".btn-close");

    if (headingEl) headingEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    if (submitBtn) {
      submitBtn.textContent = confirmText;
      submitBtn.className = `btn btn-${variant}`;
    }
    if (cancelBtn) cancelBtn.textContent = cancelText;
    if (iconEl) {
      iconEl.className = `bi ${icon} text-${variant === "secondary" ? "secondary" : variant}`;
    }

    return new Promise((resolve) => {
      let resolved = false;

      function cleanup() {
        submitBtn?.removeEventListener("click", onConfirm);
        cancelBtn?.removeEventListener("click", onCancel);
        closeBtn?.removeEventListener("click", onCancel);
        modalEl?.removeEventListener("hidden.bs.modal", onHidden);
      }

      function finish(value) {
        if (resolved) return;
        resolved = true;
        cleanup();
        resolve(value);
      }

      function onConfirm() {
        finish(true);
        hideModal(modalEl, windowRef);
      }

      function onCancel() {
        finish(false);
        hideModal(modalEl, windowRef);
      }

      function onHidden() {
        finish(false);
      }

      submitBtn?.addEventListener("click", onConfirm);
      cancelBtn?.addEventListener("click", onCancel);
      closeBtn?.addEventListener("click", onCancel);
      modalEl.addEventListener("hidden.bs.modal", onHidden, { once: true });

      showModal(modalEl, windowRef);
    });
  }

  /**
   * Prompt dialog returning Promise<string|null>.
   *
   * @param {string|{
   *   title?: string,
   *   message?: string,
   *   label?: string,
   *   defaultValue?: string,
   *   placeholder?: string,
   *   inputType?: string,
   *   helpText?: string,
   *   required?: boolean,
   *   confirmText?: string,
   *   cancelText?: string,
   *   icon?: string,
   *   variant?: string
   * }} options
   * @param {string} [fallbackDefaultValue=""]
   * @returns {Promise<string|null>}
   */
  function prompt(options, fallbackDefaultValue = "") {
    let opts;
    if (typeof options === "string") {
      opts = { message: options, defaultValue: fallbackDefaultValue };
    } else {
      opts = options || {};
    }
    const message = String(opts.message || opts.label || "");
    const defaultValue = String(opts.defaultValue ?? "");
    const placeholder = String(opts.placeholder ?? "");
    const inputType = String(opts.inputType || "text");
    const title = opts.title || translate("admin.modal.prompt_title", "Bilgi Girişi");
    const confirmText = opts.confirmText || translate("admin.modal.ok", "Tamam");
    const cancelText = opts.cancelText || translate("admin.modal.cancel", "İptal");
    const helpText = opts.helpText || "";
    const required = Boolean(opts.required);
    const icon = opts.icon || "bi-pencil-square";
    const variant = opts.variant || "primary";

    const modalEl = documentRef?.getElementById?.("panel-prompt-modal");
    if (!modalEl) {
      return Promise.resolve(
        typeof windowRef?.prompt === "function"
          ? windowRef.prompt(message, defaultValue)
          : defaultValue,
      );
    }

    const formEl = modalEl.querySelector("#panel-prompt-modal-form");
    const headingEl = modalEl.querySelector("#panel-prompt-modal-heading");
    const labelEl = modalEl.querySelector("#panel-prompt-modal-label");
    const inputEl = modalEl.querySelector("#panel-prompt-modal-input");
    const helpEl = modalEl.querySelector("#panel-prompt-modal-help");
    const submitBtn = modalEl.querySelector("#panel-prompt-modal-submit");
    const cancelBtn = modalEl.querySelector("#panel-prompt-modal-cancel");
    const iconEl = modalEl.querySelector("#panel-prompt-modal-icon");
    const closeBtn = modalEl.querySelector(".btn-close");

    if (headingEl) headingEl.textContent = title;
    if (labelEl) labelEl.textContent = message;
    if (inputEl) {
      inputEl.type = inputType;
      inputEl.value = defaultValue;
      inputEl.placeholder = placeholder;
      inputEl.required = required;
    }
    if (helpEl) {
      if (helpText) {
        helpEl.textContent = helpText;
        helpEl.hidden = false;
      } else {
        helpEl.textContent = "";
        helpEl.hidden = true;
      }
    }
    if (submitBtn) {
      submitBtn.textContent = confirmText;
      submitBtn.className = `btn btn-${variant}`;
    }
    if (cancelBtn) cancelBtn.textContent = cancelText;
    if (iconEl) {
      iconEl.className = `bi ${icon} text-${variant}`;
    }

    return new Promise((resolve) => {
      let resolved = false;

      function cleanup() {
        formEl?.removeEventListener("submit", onSubmit);
        cancelBtn?.removeEventListener("click", onCancel);
        closeBtn?.removeEventListener("click", onCancel);
        modalEl?.removeEventListener("hidden.bs.modal", onHidden);
        modalEl?.removeEventListener("shown.bs.modal", onShown);
      }

      function finish(value) {
        if (resolved) return;
        resolved = true;
        cleanup();
        resolve(value);
      }

      function onSubmit(e) {
        e?.preventDefault?.();
        finish(inputEl ? inputEl.value : "");
        hideModal(modalEl, windowRef);
      }

      function onCancel() {
        finish(null);
        hideModal(modalEl, windowRef);
      }

      function onHidden() {
        finish(null);
      }

      function onShown() {
        inputEl?.focus?.();
        inputEl?.select?.();
      }

      formEl?.addEventListener("submit", onSubmit);
      cancelBtn?.addEventListener("click", onCancel);
      closeBtn?.addEventListener("click", onCancel);
      modalEl.addEventListener("hidden.bs.modal", onHidden, { once: true });
      modalEl.addEventListener("shown.bs.modal", onShown, { once: true });

      showModal(modalEl, windowRef);
      setTimeout(() => {
        inputEl?.focus?.();
        inputEl?.select?.();
      }, 50);
    });
  }

  /**
   * Alert dialog returning Promise<void>.
   *
   * @param {string|{
   *   title?: string,
   *   message: string,
   *   okText?: string,
   *   variant?: string,
   *   icon?: string
   * }} options
   * @returns {Promise<void>}
   */
  function alert(options) {
    const opts = typeof options === "string" ? { message: options } : (options || {});
    const message = String(opts.message || "");
    const title = opts.title || translate("admin.modal.alert_title", "Bildirim");
    const okText = opts.okText || translate("admin.modal.ok", "Tamam");
    const variant = opts.variant || "primary";
    const icon = opts.icon || (variant === "danger" ? "bi-exclamation-triangle-fill" : "bi-info-circle-fill");

    const modalEl = documentRef?.getElementById?.("panel-alert-modal");
    if (!modalEl) {
      if (typeof windowRef?.alert === "function") windowRef.alert(message);
      return Promise.resolve();
    }

    const headingEl = modalEl.querySelector("#panel-alert-modal-heading");
    const messageEl = modalEl.querySelector("#panel-alert-modal-message");
    const submitBtn = modalEl.querySelector("#panel-alert-modal-submit");
    const iconEl = modalEl.querySelector("#panel-alert-modal-icon");
    const closeBtn = modalEl.querySelector(".btn-close");

    if (headingEl) headingEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    if (submitBtn) {
      submitBtn.textContent = okText;
      submitBtn.className = `btn btn-${variant}`;
    }
    if (iconEl) {
      iconEl.className = `bi ${icon} text-${variant}`;
    }

    return new Promise((resolve) => {
      let resolved = false;

      function cleanup() {
        submitBtn?.removeEventListener("click", onDismiss);
        closeBtn?.removeEventListener("click", onDismiss);
        modalEl?.removeEventListener("hidden.bs.modal", onHidden);
      }

      function finish() {
        if (resolved) return;
        resolved = true;
        cleanup();
        resolve();
      }

      function onDismiss() {
        finish();
        hideModal(modalEl, windowRef);
      }

      function onHidden() {
        finish();
      }

      submitBtn?.addEventListener("click", onDismiss);
      closeBtn?.addEventListener("click", onDismiss);
      modalEl.addEventListener("hidden.bs.modal", onHidden, { once: true });

      showModal(modalEl, windowRef);
    });
  }

  /**
   * Flexible dialog for forms with multiple fields.
   *
   * @param {{
   *   title?: string,
   *   icon?: string,
   *   variant?: string,
   *   confirmText?: string,
   *   cancelText?: string,
   *   fields: Array<{
   *     name: string,
   *     label?: string,
   *     type?: string,
   *     value?: any,
   *     placeholder?: string,
   *     required?: boolean,
   *     help?: string,
   *     min?: number|string,
   *     max?: number|string,
   *     step?: number|string,
   *     rows?: number,
   *     options?: Array<{ value: string, label: string }|string>
   *   }>
   * }} options
   * @returns {Promise<Record<string, string>|null>}
   */
  function dialog(options = {}) {
    const title = options.title || translate("admin.modal.dialog_title", "İşlem");
    const confirmText = options.confirmText || translate("admin.modal.ok", "Tamam");
    const cancelText = options.cancelText || translate("admin.modal.cancel", "İptal");
    const variant = options.variant || "primary";
    const icon = options.icon || "bi-sliders";
    const fields = Array.isArray(options.fields) ? options.fields : [];

    const modalEl = documentRef?.getElementById?.("panel-dialog-modal");
    if (!modalEl) return Promise.resolve(null);

    const formEl = modalEl.querySelector("#panel-dialog-modal-form");
    const headingEl = modalEl.querySelector("#panel-dialog-modal-heading");
    const iconEl = modalEl.querySelector("#panel-dialog-modal-icon");
    const fieldsEl = modalEl.querySelector("#panel-dialog-modal-fields");
    const submitBtn = modalEl.querySelector("#panel-dialog-modal-submit");
    const cancelBtn = modalEl.querySelector("#panel-dialog-modal-cancel");
    const closeBtn = modalEl.querySelector(".btn-close");

    if (headingEl) headingEl.textContent = title;
    if (iconEl) iconEl.className = `bi ${icon} text-${variant}`;
    if (submitBtn) {
      submitBtn.textContent = confirmText;
      submitBtn.className = `btn btn-${variant}`;
    }
    if (cancelBtn) cancelBtn.textContent = cancelText;

    if (fieldsEl) {
      fieldsEl.textContent = "";
      for (const field of fields) {
        const fieldWrapper = documentRef.createElement("div");
        fieldWrapper.className = "mb-2";
        if (field.label) {
          const label = documentRef.createElement("label");
          label.className = "form-label text-secondary-emphasis mb-1 fw-medium";
          label.textContent = field.label;
          if (field.name) label.htmlFor = `dialog-field-${field.name}`;
          fieldWrapper.appendChild(label);
        }
        let input;
        if (field.type === "textarea") {
          input = documentRef.createElement("textarea");
          input.rows = field.rows || 3;
        } else if (field.type === "select") {
          input = documentRef.createElement("select");
          input.className = "form-select shadow-none";
          for (const opt of (field.options || [])) {
            const optEl = documentRef.createElement("option");
            optEl.value = typeof opt === "object" ? opt.value : opt;
            optEl.textContent = typeof opt === "object" ? opt.label : opt;
            if (String(optEl.value) === String(field.value ?? "")) optEl.selected = true;
            input.appendChild(optEl);
          }
        } else {
          input = documentRef.createElement("input");
          input.type = field.type || "text";
        }
        if (field.type !== "select") {
          input.className = "form-control shadow-none";
        }
        if (field.name) {
          input.name = field.name;
          input.id = `dialog-field-${field.name}`;
        }
        if (field.value !== undefined && field.type !== "select") {
          input.value = field.value;
        }
        if (field.placeholder) input.placeholder = field.placeholder;
        if (field.required) input.required = true;
        if (field.min !== undefined) input.min = String(field.min);
        if (field.max !== undefined) input.max = String(field.max);
        if (field.step !== undefined) input.step = String(field.step);
        fieldWrapper.appendChild(input);

        if (field.help) {
          const help = documentRef.createElement("div");
          help.className = "form-text mt-1";
          help.textContent = field.help;
          fieldWrapper.appendChild(help);
        }
        fieldsEl.appendChild(fieldWrapper);
      }
    }

    return new Promise((resolve) => {
      let resolved = false;

      function cleanup() {
        formEl?.removeEventListener("submit", onSubmit);
        cancelBtn?.removeEventListener("click", onCancel);
        closeBtn?.removeEventListener("click", onCancel);
        modalEl?.removeEventListener("hidden.bs.modal", onHidden);
        modalEl?.removeEventListener("shown.bs.modal", onShown);
      }

      function finish(value) {
        if (resolved) return;
        resolved = true;
        cleanup();
        resolve(value);
      }

      function onSubmit(e) {
        e?.preventDefault?.();
        const result = {};
        if (formEl) {
          const FormDataCtor = windowRef?.FormData || globalThis.FormData;
          if (FormDataCtor) {
            const formData = new FormDataCtor(formEl);
            for (const [key, val] of formData.entries()) {
              result[key] = val;
            }
          }
        }
        finish(result);
        hideModal(modalEl, windowRef);
      }

      function onCancel() {
        finish(null);
        hideModal(modalEl, windowRef);
      }

      function onHidden() {
        finish(null);
      }

      function onShown() {
        const firstInput = fieldsEl?.querySelector("input, select, textarea");
        firstInput?.focus?.();
        firstInput?.select?.();
      }

      formEl?.addEventListener("submit", onSubmit);
      cancelBtn?.addEventListener("click", onCancel);
      closeBtn?.addEventListener("click", onCancel);
      modalEl.addEventListener("hidden.bs.modal", onHidden, { once: true });
      modalEl.addEventListener("shown.bs.modal", onShown, { once: true });

      showModal(modalEl, windowRef);
      setTimeout(() => {
        const firstInput = fieldsEl?.querySelector("input, select, textarea");
        firstInput?.focus?.();
        firstInput?.select?.();
      }, 50);
    });
  }

  return Object.freeze({
    confirm,
    prompt,
    alert,
    dialog,
  });
}
