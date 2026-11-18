const plnFormatter = new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' })

/** Prices travel as integer grosze, so 4999 is shown as "49,99 zł". */
export function formatPrice(grosze: number): string {
  return plnFormatter.format(grosze / 100)
}

/**
 * Turns what the user typed in PLN ("49,99" or "50") into grosze.
 * Returns null when the text is not a price with at most two decimals.
 */
export function plnToGrosze(text: string): number | null {
  const normalized = text.trim().replace(',', '.')

  if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
    return null
  }

  return Math.round(Number(normalized) * 100)
}

/** The reverse, for filling an edit form: 4999 becomes "49.99". */
export function groszeToPln(grosze: number): string {
  return (grosze / 100).toFixed(2)
}
