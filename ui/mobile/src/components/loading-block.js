/**
 * Renders a stable loading placeholder block.
 */
export function renderLoadingBlock(message = 'Loading...') {
  return `
    <div class="block block-strong mobile-feedback-block">
      <div class="preloader color-blue"></div>
      <p>${message}</p>
    </div>
  `;
}
