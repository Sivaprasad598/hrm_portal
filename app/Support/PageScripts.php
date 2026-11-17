<?php

namespace App\Support;

/**
 * Makes a page's inline scripts safe to run more than once in the same document.
 *
 * Page content can be loaded without reloading the whole page (the sidebar and the
 * top bar stay: sections/page-swap). The page's scripts then run in a document
 * where an earlier page's scripts already ran - and a top-level `const` or `let`
 * may only be declared once per document: the second page that declares
 * `const showTable` (20 pages do) would stop with "already declared".
 *
 * So the top-level `const` / `let` of a page's scripts are written as `var`:
 * the same global name with the same value, visible to the same code (the
 * pop-ups' scripts call these by name), and it may be declared again. Nothing
 * inside a function or a block is touched.
 *
 * Careful by design: a script is changed only if it reads cleanly from start to
 * end (every bracket, string, template and comment closed); otherwise it is
 * returned exactly as written.
 */
class PageScripts
{

    /**
     * Names that already mean something on `window`: as `var` they would write to
     * the browser's own property (`var location = ...` navigates away), so a
     * declaration of one of these is left as it is.
     */
    protected const RESERVED = [
        'name', 'status', 'location', 'top', 'parent', 'self', 'window', 'document', 'history', 'event', 'length', 'closed',
        'origin', 'screen', 'opener', 'frames', 'navigator', 'external', 'open', 'close', 'stop', 'focus', 'blur', 'print',
        'find', 'scroll', 'alert', 'confirm', 'prompt', 'fetch', 'performance', 'crypto', 'caches', 'console', 'toolbar',
        'menubar', 'statusbar', 'locationbar', 'scrollbars', 'innerWidth', 'innerHeight', 'outerWidth', 'outerHeight',
        'scrollX', 'scrollY', 'screenX', 'screenY', 'localStorage', 'sessionStorage', 'customElements', 'frameElement',
    ];

    /** Words after which a "/" starts a regular expression, not a division. */
    protected const BEFORE_REGEX = ['return', 'typeof', 'case', 'do', 'else', 'in', 'of', 'void', 'delete', 'new', 'throw', 'instanceof', 'yield', 'await'];

    /** Every inline script in a piece of HTML, globalised (see the class note). */
    public static function globalize(string $html): string
    {
        if (stripos($html, '<script') === false) {
            return $html;
        }

        return preg_replace_callback('#(<script\b([^>]*)>)(.*?)(</script>)#is', function ($m) {
            $attributes = $m[2];

            // only plain inline JavaScript: not a file, JSON, a template or a module
            if (preg_match('/\bsrc\s*=/i', $attributes)
                || (preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attributes, $type)
                    && !in_array(strtolower($type[1]), ['text/javascript', 'application/javascript'], true))) {
                return $m[0];
            }

            return $m[1] . self::javascript($m[3]) . $m[4];
        }, $html) ?? $html;
    }

    /** One script's text with its top-level `const` / `let` written as `var`. */
    public static function javascript(string $js): string
    {
        if (!preg_match('/\b(?:const|let)\s/', $js)) {
            return $js;
        }

        $length = strlen($js);
        $depth = 0;            // open ( [ {
        $templates = [];       // for each open `template`: the depth its ${ ... } returns to
        $edits = [];           // [offset, length of the keyword]
        $lastWord = '';        // the identifier just read
        $lastSignificant = ''; // the last character that was not white space or a comment
        $i = 0;

        while ($i < $length) {
            $c = $js[$i];

            // white space
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $i++;
                continue;
            }

            // comments
            if ($c === '/' && $i + 1 < $length && $js[$i + 1] === '/') {
                $end = strpos($js, "\n", $i);
                $i = $end === false ? $length : $end;
                continue;
            }

            if ($c === '/' && $i + 1 < $length && $js[$i + 1] === '*') {
                $end = strpos($js, '*/', $i + 2);

                if ($end === false) {
                    return $js;
                }

                $i = $end + 2;
                continue;
            }

            // strings
            if ($c === '"' || $c === "'") {
                $i = self::skipString($js, $i, $c);

                if ($i === null) {
                    return $js;
                }

                $lastSignificant = $c;
                $lastWord = '';
                continue;
            }

            // template literal: its text is skipped, its ${ expressions } are read as code
            if ($c === '`' || ($c === '}' && $templates && end($templates) === $depth)) {
                if ($c === '}') {
                    array_pop($templates);   // back inside the template's text
                }

                $i = self::skipTemplateText($js, $i + 1);

                if ($i === null) {
                    return $js;
                }

                if ($js[$i] === '`') {
                    $i++;                     // the template is closed
                } else {
                    $templates[] = $depth;    // "${": code until the matching "}"
                    $i += 2;
                }

                $lastSignificant = '`';
                $lastWord = '';
                continue;
            }

            // a regular expression (a "/" where a value is expected)
            if ($c === '/') {
                $valueBefore = $lastSignificant !== '' && (ctype_alnum($lastSignificant) || strpos('_$)]}"\'`', $lastSignificant) !== false)
                    && !in_array($lastWord, self::BEFORE_REGEX, true);

                if (!$valueBefore) {
                    $i = self::skipRegex($js, $i);

                    if ($i === null) {
                        return $js;
                    }

                    $lastSignificant = '/';
                    $lastWord = '';
                    continue;
                }
            }

            // identifiers and keywords
            if (ctype_alpha($c) || $c === '_' || $c === '$') {
                $start = $i;

                while ($i < $length && (ctype_alnum($js[$i]) || $js[$i] === '_' || $js[$i] === '$')) {
                    $i++;
                }

                $word = substr($js, $start, $i - $start);

                // a declaration at the top level, not a property (obj.let) and not a label or key (let:)
                if (($word === 'const' || $word === 'let') && $depth === 0 && !$templates && $lastSignificant !== '.'
                    && preg_match('/\G\s+([A-Za-z_$][\w$]*)\s*(?:[=;,\n]|$)/', $js, $name, 0, $i)
                    && !in_array($name[1], self::RESERVED, true) && !str_starts_with($name[1], 'on')) {
                    $edits[] = [$start, strlen($word)];
                }

                $lastWord = $word;
                $lastSignificant = $js[$i - 1];
                continue;
            }

            if ($c === '(' || $c === '[' || $c === '{') {
                $depth++;
            } elseif ($c === ')' || $c === ']' || $c === '}') {
                $depth--;

                if ($depth < 0) {
                    return $js;
                }
            }

            $lastSignificant = $c;
            $lastWord = '';
            $i++;
        }

        // not read cleanly to the end: leave the script exactly as written
        if ($depth !== 0 || $templates || !$edits) {
            return $js;
        }

        foreach (array_reverse($edits) as [$offset, $size]) {
            // same length as the keyword, so line and column numbers in errors stay true
            $js = substr($js, 0, $offset) . str_pad('var', $size) . substr($js, $offset + $size);
        }

        return $js;
    }

    /** The offset just after a quoted string, or null if it never closes on its line. */
    protected static function skipString(string $js, int $i, string $quote): ?int
    {
        $length = strlen($js);

        for ($i++; $i < $length; $i++) {
            $c = $js[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === $quote) {
                return $i + 1;
            } elseif ($c === "\n") {
                return null;
            }
        }

        return null;
    }

    /** From inside a template's text: the offset of its closing ` or of the next "${", or null. */
    protected static function skipTemplateText(string $js, int $i): ?int
    {
        $length = strlen($js);

        for (; $i < $length; $i++) {
            $c = $js[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === '`' || ($c === '$' && $i + 1 < $length && $js[$i + 1] === '{')) {
                return $i;
            }
        }

        return null;
    }

    /** The offset just after a regular expression and its flags, or null if it never closes on its line. */
    protected static function skipRegex(string $js, int $i): ?int
    {
        $length = strlen($js);
        $inClass = false;

        for ($i++; $i < $length; $i++) {
            $c = $js[$i];

            if ($c === '\\') {
                $i++;
            } elseif ($c === "\n") {
                return null;
            } elseif ($c === '[') {
                $inClass = true;
            } elseif ($c === ']') {
                $inClass = false;
            } elseif ($c === '/' && !$inClass) {
                $i++;

                while ($i < $length && ctype_alpha($js[$i])) {
                    $i++;
                }

                return $i;
            }
        }

        return null;
    }

}
