// Inspect source attributes before a browser silently discards duplicates.
export function auditTemplates(html, dictionaries) {
  const errors = [];
  const keys = new Set();
  const tokens = /<!--[\s\S]*?-->|<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<([a-z][\w:-]*)((?:"[^"]*"|'[^']*'|[^'">])*)>/gi;
  for (const match of html.matchAll(tokens)) {
    if (!match[1]) continue;
    const line = html.slice(0, match.index).split("\n").length;
    const attributes = new Map();
    const pattern = /([^\s=/'"<>]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g;
    for (const attribute of match[2].matchAll(pattern)) {
      const name = attribute[1].toLowerCase();
      if (attributes.has(name)) errors.push(`Line ${line}: duplicate ${name} on <${match[1]}>`);
      attributes.set(name, attribute[2] ?? attribute[3] ?? attribute[4] ?? "");
    }
    const textKey = attributes.get("data-i18n");
    if (textKey && !textKey.includes("${")) keys.add(textKey);
    for (const pair of (attributes.get("data-i18n-attr") || "").split(",")) {
      if (!pair.trim()) continue;
      const parts = pair.split(":").map((part) => part.trim());
      if (parts.length !== 2 || !parts[0] || !parts[1]) {
        errors.push(`Line ${line}: invalid data-i18n-attr mapping ${pair}`);
      } else if (!parts[1].includes("${")) {
        keys.add(parts[1]);
      }
    }
  }
  for (const [locale, dictionary] of Object.entries(dictionaries)) {
    for (const key of keys) {
      if (typeof dictionary[key] !== "string" || !dictionary[key].trim()) {
        errors.push(`${locale}: missing or empty translation ${key}`);
      }
    }
  }
  return { errors, keys };
}
