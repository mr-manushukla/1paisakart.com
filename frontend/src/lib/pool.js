/**
 * A pool's fill level stays hidden until it is this full. Below the threshold a
 * half-empty bar reads as "nobody's here" and puts people off; above it the same
 * number is social proof, so it appears together with an urgency line.
 *
 * One constant, used by every surface that could leak the fill level — the
 * progress bar AND the "Who's in the pool" seat map — so they can never drift
 * apart and quietly reveal it in one place but not the other.
 */
export const REVEAL_AT_PCT = 60

export const poolPct = (filled, size) =>
  Math.min(100, Math.round(((Number(filled) || 0) / Math.max(1, Number(size) || 1)) * 100))

/** Is this pool full enough to show its progress publicly? */
export const poolRevealed = (filled, size) => poolPct(filled, size) >= REVEAL_AT_PCT

/** The nudge shown once the fill level is public. */
export const poolUrgency = (filled, size) =>
  poolPct(filled, size) >= 85
    ? 'Almost Full – Reserve Your Spot Now!'
    : 'Hurry! Filling Fast'
