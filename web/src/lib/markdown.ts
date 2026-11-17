import DOMPurify from 'dompurify';
import { marked } from 'marked';

/**
 * Markdown → 消毒后的 HTML 字符串。marked 对原始 HTML 放行，
 * 所以消毒这一步不可省；产物仍须由调用方决定怎么塞进 DOM
 * （dangerouslySetInnerHTML 只在走这里之后允许）。
 */
export function renderMarkdown(markdown: string): string {
  const html = marked.parse(markdown, { async: false });
  return DOMPurify.sanitize(html);
}
