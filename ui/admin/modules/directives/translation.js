export function createTranslationModule({ defineModule, attr, i18n }) {
  return defineModule({
    name: "panel-i18n",
    version: "1.0.0",
    triggers: [
      attr("data-i18n", {
        phase: "link",
        read: (element) => String(element.getAttribute("data-i18n") || ""),
        setup: (element, key) => {
          if (key && i18n.has(key)) element.textContent = i18n.t(key);
        },
      }),
      attr("data-i18n-attr", {
        phase: "link",
        read: (element) => String(element.getAttribute("data-i18n-attr") || ""),
        setup: (element, value) => {
          for (const pair of String(value).split(",")) {
            const [attribute, key] = pair.split(":").map((part) => part.trim());
            if (attribute && key && i18n.has(key)) {
              element.setAttribute(attribute, i18n.t(key));
            }
          }
        },
      }),
    ],
  });
}
