<?php
declare(strict_types=1);

/**
 * Normalize Hindi consonants by collapsing related varg members to a base sound.
 * Keeps vowels and matras untouched so word shapes remain intact for indexing/search.
 *
 * उदाहरण (Examples):
 *   normalize_hindi_phonetics('खाना') → 'काना'
 *   normalize_hindi_phonetics('घरेलू') → 'गरेलू'
 *   normalize_hindi_phonetics('भाषा') → 'बासा'
 */
function normalize_hindi_phonetics(string $text): string
{
    static $map = null;
    static $nuktaSafe = null;

    if ($map === null) {
        $map = [
            // क-वर्ग (Velar)
            'ख' => 'क',
            'घ' => 'ग',
            'ङ' => 'ग',
            // च-वर्ग (Palatal)
            'छ' => 'च',
            'झ' => 'ज',
            'ञ' => 'ज',
            // ट-वर्ग (Retroflex)
            'ठ' => 'ट',
            'ढ' => 'ड',
            'ण' => 'ड',
            // त-वर्ग (Dental)
            'थ' => 'त',
            'ध' => 'द',
            // प-वर्ग (Labial)
            'फ' => 'प',
            'भ' => 'ब',
            // श-स वर्ग (Sibilants)
            'श' => 'स',
            'ष' => 'स',
        ];

        // Characters that should not change if followed by nukta (़)
        $nuktaSafe = ['फ', 'ढ', 'ड'];
    }

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        return $text;
    }

    $result = '';
    $count = count($chars);
    for ($i = 0; $i < $count; $i++) {
        $char = $chars[$i];
        $next = $chars[$i + 1] ?? '';

        if ($next === '़' && in_array($char, $nuktaSafe, true)) {
            $result .= $char;
            continue;
        }

        $result .= $map[$char] ?? $char;
    }

    return $result;
}
