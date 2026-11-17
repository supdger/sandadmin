import type { CSSProperties, ReactNode } from 'react';

import { useHighlight } from './useHighlight';

export interface HighlightTextProps {
  text: string;
  keyword?: string;
  /** 默认大小写不敏感。 */
  caseSensitive?: boolean;
  /** 命中片段 <mark> 的样式覆写。 */
  markStyle?: CSSProperties;
  className?: string;
}

/**
 * 搜索关键词高亮：命中片段渲染成 <mark>。走 React 文本节点，
 * 不碰 dangerouslySetInnerHTML，天然免疫 XSS。
 */
export function HighlightText({
  text,
  keyword,
  caseSensitive = false,
  markStyle,
  className,
}: HighlightTextProps) {
  const segments = useHighlight(text, keyword ?? '', caseSensitive);

  const nodes: ReactNode[] = segments.map((segment, index) =>
    segment.matched ? (
      <mark
        // 片段内容可重复，用顺序下标做 key。
        key={index}
        style={{
          background: 'var(--semi-color-warning-light-default)',
          color: 'inherit',
          padding: 0,
          ...markStyle,
        }}
      >
        {segment.text}
      </mark>
    ) : (
      <span key={index}>{segment.text}</span>
    ),
  );

  return <span className={className}>{nodes}</span>;
}
