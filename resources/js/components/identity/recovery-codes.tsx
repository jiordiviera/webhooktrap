import { useState } from 'react';
import { Button } from './form';
import { Glyph } from './glyph';

/*
 * Ten recovery codes, shown once (S-17B `codes`, DS RecoveryCodeGrid): a
 * numbered 2 × 5 grid in Martian Mono with Download, Copy and Print; Done
 * stays disabled until "I've saved these codes" is ticked.
 */

/** Groups a code so screen readers pause ("x7vc2-y4bnq" stays as printed). */
function spoken(code: string): string {
    return code.split('').join(' ');
}

export function RecoveryCodes({
    codes,
    saved,
    onSavedChange,
}: {
    codes: string[];
    saved: boolean;
    onSavedChange: (saved: boolean) => void;
}) {
    const [copied, setCopied] = useState(false);
    const [printing, setPrinting] = useState(false);
    const text = `Webhook Trap recovery codes\n\n${codes.join('\n')}\n`;

    const download = () => {
        const url = URL.createObjectURL(
            new Blob([text], { type: 'text/plain' }),
        );
        const a = document.createElement('a');
        a.href = url;
        a.download = 'webhooktrap-recovery-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    };

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
        } catch {
            setCopied(false);
        }
    };

    const print = () => {
        setPrinting(true);
        window.setTimeout(() => {
            window.print();
            setPrinting(false);
        }, 0);
    };

    return (
        <div className="tt-col" style={{ gap: 12 }}>
            <ol
                className={`tt-codegrid${printing ? ' tt-print' : ''}`}
                aria-label="Recovery codes"
            >
                {codes.map((code, i) => (
                    <li key={code}>
                        <span className="n" aria-hidden="true">
                            {i + 1}
                        </span>
                        <span aria-label={spoken(code)}>{code}</span>
                    </li>
                ))}
            </ol>
            <div className="tt-actions" style={{ gap: 8 }}>
                <Button size="sm" onClick={download}>
                    Download
                </Button>
                <Button size="sm" onClick={() => void copy()}>
                    <Glyph name="copy" />
                    Copy
                </Button>
                <Button size="sm" onClick={print}>
                    Print
                </Button>
                <span className="tt-hint" role="status">
                    {copied ? 'Copied.' : ''}
                </span>
            </div>
            <label className="tt-checkrow">
                <input
                    type="checkbox"
                    className="tt-check"
                    checked={saved}
                    onChange={(e) => onSavedChange(e.target.checked)}
                />
                I&apos;ve saved these codes
            </label>
        </div>
    );
}
