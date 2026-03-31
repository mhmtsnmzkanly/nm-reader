/**
 * Renders the compact bottom navigation used on primary non-reader screens.
 */
export function renderBottomNav(active = 'home') {
  const items = [
    { key: 'home', href: '/home/', icon: 'house_fill', label: 'Home' },
    { key: 'library', href: '/library/', icon: 'books_vertical_fill', label: 'Library' },
    { key: 'history', href: '/history/', icon: 'clock_fill', label: 'History' },
    { key: 'wallet', href: '/wallet/', icon: 'creditcard_fill', label: 'Wallet' },
    { key: 'settings', href: '/settings/', icon: 'gear_alt_fill', label: 'Settings' },
  ];

  return `
    <div class="toolbar toolbar-bottom mobile-bottom-nav">
      <div class="toolbar-inner">
        ${items.map((item) => `
          <a href="${item.href}" class="link ${item.key === active ? 'tab-link-active' : ''}">
            <i class="f7-icons">${item.icon}</i>
            <span>${item.label}</span>
          </a>
        `).join('')}
      </div>
    </div>
  `;
}
