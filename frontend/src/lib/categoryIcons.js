/**
 * Emoji shown beside a category name.
 *
 * An admin-set `icon` on the category always wins; this map is the fallback so
 * a category created before icons existed — or by an admin who skipped the
 * field — still gets something better than a generic tag. Matching is
 * case-insensitive and also tries a partial match, so "Men's Footwear" picks up
 * the footwear icon.
 *
 * One map, used by the header strip and the home tiles, so the same category
 * never shows two different icons.
 */
const ICONS = {
  electronics: '📱', mobiles: '📱', laptops: '💻', computers: '💻', gaming: '🎮',
  cameras: '📷', audio: '🎧', headphones: '🎧', appliances: '🔌', tv: '📺',
  fashion: '👕', clothing: '👕', men: '👔', women: '👗', kids: '🧒',
  footwear: '👟', shoes: '👟', watches: '⌚', jewellery: '💍', jewelry: '💍',
  bags: '👜', luggage: '🧳', accessories: '🕶️',
  beauty: '💄', personal: '🧴', health: '💊', grooming: '🪒', fragrance: '🌸',
  grocery: '🛒', food: '🍫', beverages: '🥤',
  home: '🏠', kitchen: '🍳', furniture: '🛋️', decor: '🖼️', garden: '🪴',
  sports: '⚽', fitness: '🏋️', outdoor: '⛺', cycles: '🚲',
  toys: '🧸', baby: '🍼', books: '📚', stationery: '✏️', music: '🎵',
  automotive: '🚗', tools: '🔧', pets: '🐾', travel: '✈️', office: '🗂️',
}

const DEFAULT_ICON = '🏷️'

/** @param {{name?: string, icon?: string}} category */
export function categoryIcon(category) {
  if (category?.icon) return category.icon

  const name = String(category?.name ?? '').toLowerCase()
  if (!name) return DEFAULT_ICON

  if (ICONS[name]) return ICONS[name]

  // Longest match wins, so "Home & Kitchen" picks kitchen (🍳) over the broader
  // home (🏠), and "Men's Footwear" picks footwear over men.
  const hit = Object.keys(ICONS)
    .filter((k) => name.includes(k))
    .sort((a, b) => b.length - a.length)[0]

  return hit ? ICONS[hit] : DEFAULT_ICON
}
