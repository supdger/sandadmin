import { describe, expect, it } from 'vitest';

import { sanitizeAnnouncementHtml } from './announcementHtml';

/** 把清洗结果再解析一次，直接断言 DOM 结构而不是字符串。 */
function parse(html: string): Document {
  return new DOMParser().parseFromString(html, 'text/html');
}

describe('sanitizeAnnouncementHtml', () => {
  it('keeps the WangEditor-produced tag set', () => {
    const out = sanitizeAnnouncementHtml(
      '<p>hi<strong>bold</strong></p><h2>t</h2><ul><li>x</li></ul>' +
        '<blockquote><code>c</code></blockquote><table><tr><td>d</td></tr></table>' +
        '<hr><a href="https://a.b" target="_blank" rel="noopener">l</a>' +
        '<img src="https://a.b/x.png" alt="x">',
    );
    const doc = parse(out);
    expect(doc.querySelector('strong')?.textContent).toBe('bold');
    expect(doc.querySelector('a')?.getAttribute('href')).toBe('https://a.b');
    expect(doc.querySelector('img')?.getAttribute('src')).toBe('https://a.b/x.png');
  });

  it('removes script/style/iframe and event handlers', () => {
    const out = sanitizeAnnouncementHtml(
      '<p onclick="x()">a</p><script>alert(1)</script><style>p{}</style>' +
        '<iframe src="https://x"></iframe><img src="x" onerror="y()">',
    );
    const doc = parse(out);
    expect(doc.querySelector('script')).toBeNull();
    expect(doc.querySelector('style')).toBeNull();
    expect(doc.querySelector('iframe')).toBeNull();
    expect(doc.querySelector('[onclick]')).toBeNull();
    expect(doc.querySelector('[onerror]')).toBeNull();
    expect(doc.querySelector('img')).toBeTruthy();
  });

  it('drops javascript: URLs including whitespace/tab bypasses', () => {
    for (const href of ['javascript:alert(1)', 'java\tscript:alert(1)', 'java\nscript:alert(1)', '  javascript:alert(1)']) {
      const out = sanitizeAnnouncementHtml(`<a href="${href}">x</a>`);
      const a = parse(out).querySelector('a');
      expect(a?.getAttribute('href') ?? '').not.toContain('javascript');
    }
  });

  it('drops data: and vbscript: URLs on img src', () => {
    const out = sanitizeAnnouncementHtml(
      '<img src="data:image/svg+xml,<svg onload=alert(1)>" alt="a">' +
        '<a href="vbscript:msgbox(1)">v</a>',
    );
    const doc = parse(out);
    expect(doc.querySelector('img')?.getAttribute('src') ?? '').not.toContain('data:');
    expect(doc.querySelector('a')?.getAttribute('href') ?? '').not.toContain('vbscript');
  });

  it('removes formaction and form controls entirely', () => {
    const out = sanitizeAnnouncementHtml(
      '<button formaction="javascript:alert(1)">b</button><form action="https://x"><input type="text"></form>',
    );
    const doc = parse(out);
    expect(doc.querySelector('button')).toBeNull();
    expect(doc.querySelector('form')).toBeNull();
    expect(doc.querySelector('input')).toBeNull();
    expect(doc.querySelector('[formaction]')).toBeNull();
  });

  it('removes style/class/id attributes but keeps the element', () => {
    const out = sanitizeAnnouncementHtml(
      '<p style="color:red" class="x" id="y">text</p>',
    );
    const node = parse(out).querySelector('p');
    expect(node?.textContent).toBe('text');
    expect(node?.getAttribute('style')).toBeNull();
    expect(node?.getAttribute('class')).toBeNull();
    expect(node?.getAttribute('id')).toBeNull();
  });

  it('keeps only href/target/rel on links', () => {
    const out = sanitizeAnnouncementHtml(
      '<a href="https://ok" target="_blank" rel="noopener" onclick="x()" style="color:red" data-x="1">l</a>',
    );
    const a = parse(out).querySelector('a');
    expect(a?.getAttribute('href')).toBe('https://ok');
    expect(a?.getAttribute('target')).toBe('_blank');
    expect(a?.getAttribute('onclick')).toBeNull();
    expect(a?.getAttribute('style')).toBeNull();
    expect(a?.getAttribute('data-x')).toBeNull();
  });

  it('strips attributes from disallowed-but-text-bearing tags content', () => {
    const out = sanitizeAnnouncementHtml(
      '<div><svg><a xlink:href="javascript:alert(1)"><circle r="10"/></a></svg>ok</div>',
    );
    const doc = parse(out);
    expect(doc.querySelector('svg')).toBeNull();
    expect(doc.querySelector('a')).toBeNull();
  });

  it('returns empty-ish output for empty input', () => {
    expect(sanitizeAnnouncementHtml('')).toBe('');
    expect(sanitizeAnnouncementHtml('<script>1</script>')).not.toContain('script');
  });
});
