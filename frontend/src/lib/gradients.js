/**
 * Hero-slide backgrounds an admin can pick from.
 *
 * These must stay LITERAL class strings in a source file — Tailwind scans the
 * source and drops any class it can't see, so a gradient typed into the admin
 * form would simply not exist in the CSS bundle. Hence presets, not free text.
 * Keys must match `config('slides.gradients')` on the backend.
 */
export const GRADIENTS = {
  green: 'from-brand-600 to-brand-800',
  dark: 'from-slate-800 to-slate-950',
  amber: 'from-accent-500 to-accent-600',
  blue: 'from-sky-700 to-indigo-900',
  rose: 'from-rose-600 to-rose-900',
}

export const gradientClass = (key) => GRADIENTS[key] ?? GRADIENTS.green

/** '*fragment*' marks highlighted text — even indexes are plain, odd are highlighted. */
export const headingParts = (heading) => String(heading ?? '').split(/\*(.+?)\*/)
