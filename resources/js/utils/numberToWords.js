const ONES = [
    '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
    'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
    'Seventeen', 'Eighteen', 'Nineteen',
];
const TENS = [
    '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
];

function twoDigitsToWords(n) {
    if (n < 20) return ONES[n];
    const tens = Math.floor(n / 10);
    const ones = n % 10;
    return TENS[tens] + (ones ? ' ' + ONES[ones] : '');
}

function threeDigitsToWords(n) {
    const hundred = Math.floor(n / 100);
    const rest = n % 100;
    let words = hundred ? ONES[hundred] + ' Hundred' : '';
    if (rest) words += (words ? ' ' : '') + twoDigitsToWords(rest);
    return words;
}

// Bangladeshi grouping: crore (1,00,00,000) / lakh (1,00,000) / thousand / hundred
function integerToWords(n) {
    if (n === 0) return 'Zero';

    const crore = Math.floor(n / 10000000);
    n %= 10000000;
    const lakh = Math.floor(n / 100000);
    n %= 100000;
    const thousand = Math.floor(n / 1000);
    n %= 1000;
    const hundred = n;

    const parts = [];
    if (crore) parts.push(threeDigitsToWords(crore) + ' Crore');
    if (lakh) parts.push(twoDigitsToWords(lakh) + ' Lakh');
    if (thousand) parts.push(twoDigitsToWords(thousand) + ' Thousand');
    if (hundred) parts.push(threeDigitsToWords(hundred));

    return parts.join(' ');
}

/**
 * Converts a numeric amount to Taka words, e.g. 3500 -> "Three Thousand Five Hundred Taka Only".
 * Returns '' for empty/invalid/zero input.
 */
export function amountToTakaWords(value) {
    const num = parseFloat(String(value).replace(/,/g, ''));
    if (!num || num <= 0 || !isFinite(num)) return '';

    const whole = Math.floor(num);
    const paisa = Math.round((num - whole) * 100);

    let words = integerToWords(whole) + ' Taka';
    if (paisa) words += ' ' + integerToWords(paisa) + ' Paisa';
    words += ' Only';

    return words;
}
