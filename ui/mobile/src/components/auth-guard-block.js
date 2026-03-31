/**
 * Renders a guest guard for protected pages instead of crashing into 401 responses.
 */
export function renderAuthGuardBlock($h) {
  return $h`
    <div class="block block-strong mobile-feedback-block">
      <div class="mobile-feedback-title">Login required</div>
      <p>This screen needs an authenticated mobile session.</p>
      <a href="/login/" class="button button-fill">Go to login</a>
    </div>
  `;
}
