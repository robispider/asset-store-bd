/** Read the digits the application renders in either supported language. */
export function interfaceNumber(value) {
  const latin = String(value).replace(/[০-৯]/g, digit => String('০১২৩৪৫৬৭৮৯'.indexOf(digit)));
  const match = latin.replace(/,/g, '').match(/[+−-]?\d+(?:\.\d+)?/);
  if (!match) throw new Error('Assertion failed: interface value does not contain a number');
  return Number(match[0].replace('−', '-'));
}
