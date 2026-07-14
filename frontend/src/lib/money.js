// All money from the API is integer paise. One formatter, used everywhere.
export function money(paise) {
  const rupees = (paise ?? 0) / 100
  return '₹' + rupees.toLocaleString('en-IN', {
    minimumFractionDigits: Number.isInteger(rupees) ? 0 : 2,
    maximumFractionDigits: 2,
  })
}
