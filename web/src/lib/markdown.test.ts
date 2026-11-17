import { describe, expect, it } from 'vitest';

import { renderMarkdown } from './markdown';

describe('renderMarkdown', () => {
  it('renders basic markdown to html', () => {
    const html = renderMarkdown('**加粗** 和 *斜体*');
    expect(html).toContain('<strong>加粗</strong>');
    expect(html).toContain('<em>斜体</em>');
  });

  it('strips script tags from raw html', () => {
    const html = renderMarkdown('正文 <script>alert(1)</script>');
    expect(html).not.toContain('<script');
    expect(html).toContain('正文');
  });

  it('strips event-handler attributes', () => {
    const html = renderMarkdown('<img src="x" onerror="alert(1)">');
    expect(html).not.toContain('onerror');
  });
});
