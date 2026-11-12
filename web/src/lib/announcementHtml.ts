import DOMPurify from 'dompurify';

// 公告正文白名单：只放 WangEditor 实际产出的标签/属性。
// 标签外的一切（script/style/iframe/form/input/on* 属性/style/class/data: 等）
// 由 DOMPurify 加下面的属性钩子双重剥掉，不在这里逐条列黑名单。
const ALLOWED_TAGS = [
  'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
  'ul', 'ol', 'li', 'blockquote', 'code', 'pre',
  'table', 'thead', 'tbody', 'tr', 'td', 'th',
  'span', 'div', 'a', 'img', 'hr',
] as const;

const ALLOWED_ATTR = [
  // a：只保留地址与新窗口行为
  'href', 'target', 'rel',
  // img：只保留来源与替代文本
  'src', 'alt',
] as const;

// href/src 允许协议：http(s)/mailto/相对与站内路径；data:/javascript:/vbscript:
// 一律拒掉。匹配前先吃掉空白与控制字符，挡住 "java\tscript:" 这类绕过。
const URI_SCHEME_SAFE = /^(https?:|mailto:|\/|#|\.)/i;

const URI_ATTRS = ['href', 'src', 'xlink:href', 'action', 'formaction', 'background', 'poster'] as const;
const TARGET_VALUES = new Set(['_blank', '_self', '_parent', '_top']);

// 协议检查挂在属性清洗之后：DOMPurify 自带的 URI 正则对 img data: URI 默认放行，
// 而契约要求禁掉，所以这里做最终判定。
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
  if (!(node instanceof Element)) {
    return;
  }
  for (const attr of URI_ATTRS) {
    const raw = node.getAttribute(attr);
    if (raw === null) {
      continue;
    }
    // eslint-disable-next-line no-control-regex -- 刻意匹配控制字符用于安全归一化
    const normalized = raw.replace(/[\u0000-\u0020\u007f]+/g, '');
    if (!URI_SCHEME_SAFE.test(normalized)) {
      node.removeAttribute(attr);
    }
  }
  const target = node.getAttribute('target');
  if (target !== null && !TARGET_VALUES.has(target)) {
    node.removeAttribute('target');
  }
});

/**
 * 公告富文本清洗。后端落库时已按同一白名单洗过一遍；前端渲染前再洗，
 * 双重防御——任何一端被绕过都不会直接进 DOM。
 */
export function sanitizeAnnouncementHtml(html: string): string {
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: [...ALLOWED_TAGS],
    ALLOWED_ATTR: [...ALLOWED_ATTR],
    ALLOW_DATA_ATTR: false,
  });
}
