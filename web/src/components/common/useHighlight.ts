import { useMemo } from 'react';

export interface HighlightSegment {
  text: string;
  matched: boolean;
}

/**
 * 按关键词把文本切成命中/未命中片段。匹配默认大小写不敏感；
 * 空关键词返回整段原文。
 */
export function splitByKeyword(
  text: string,
  keyword: string,
  caseSensitive = false,
): HighlightSegment[] {
  const needle = caseSensitive ? keyword : keyword.toLowerCase();
  if (!needle) {
    return [{ text, matched: false }];
  }
  const haystack = caseSensitive ? text : text.toLowerCase();
  const segments: HighlightSegment[] = [];
  let cursor = 0;
  for (;;) {
    const hit = haystack.indexOf(needle, cursor);
    if (hit < 0) {
      break;
    }
    if (hit > cursor) {
      segments.push({ text: text.slice(cursor, hit), matched: false });
    }
    segments.push({ text: text.slice(hit, hit + needle.length), matched: true });
    cursor = hit + needle.length;
  }
  if (cursor < text.length) {
    segments.push({ text: text.slice(cursor), matched: false });
  }
  return segments;
}

/** 搜索词高亮用的分段 hook；等价于 Vue 指令的高亮机制。 */
export function useHighlight(
  text: string,
  keyword: string,
  caseSensitive = false,
): HighlightSegment[] {
  return useMemo(
    () => splitByKeyword(text, keyword, caseSensitive),
    [text, keyword, caseSensitive],
  );
}
