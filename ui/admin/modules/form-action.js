export function bindFormAction(form, submitAction, { onError, dirtyGuard } = {}) {
  if (!form || typeof submitAction !== "function") return () => {};
  let inFlight = false;
  const unregisterGuard = dirtyGuard?.register?.(form);
  const onSubmit = async (event) => {
    event.preventDefault();
    if (inFlight) return;
    inFlight = true;
    const controls = Array.from(form.querySelectorAll("button[type=submit], input[type=submit]"));
    const previousDisabled = controls.map((control) => control.disabled);
    controls.forEach((control) => {
      control.disabled = true;
      control.setAttribute("aria-busy", "true");
    });
    form.setAttribute("aria-busy", "true");
    try {
      await submitAction(new FormData(form), form, event);
      dirtyGuard?.markClean?.();
    } catch (error) {
      if (error?.name !== "AbortError") onError?.(error);
    } finally {
      controls.forEach((control, index) => {
        control.disabled = previousDisabled[index];
        control.setAttribute("aria-busy", "false");
      });
      form.setAttribute("aria-busy", "false");
      inFlight = false;
    }
  };
  form.addEventListener("submit", onSubmit);
  return () => {
    unregisterGuard?.();
    form.removeEventListener("submit", onSubmit);
  };
}
