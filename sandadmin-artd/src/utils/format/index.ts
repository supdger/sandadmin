/**
 * 人话化展示：文件大小、MIME 类型、时间精度。
 * 后端返回原始值（size_byte 字节数、mime_type 标准串、
 * 'YYYY-MM-DD HH:mm:ss' 墙钟时间），列表层统一在这里转换。
 */

const FILE_SIZE_UNITS = ['千字节', '兆字节', '吉字节', '太字节'] as const

/** 字节数 → 「73 字节」「1.5 千字节」；无法解释时返回 —。 */
export function formatFileSize(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || !Number.isFinite(bytes) || bytes < 0) return '—'
  if (bytes < 1024) return `${bytes} 字节`
  let value = bytes / 1024
  let unitIndex = 0
  while (value >= 1024 && unitIndex < FILE_SIZE_UNITS.length - 1) {
    value /= 1024
    unitIndex += 1
  }
  const text = value >= 100 ? String(Math.round(value)) : value.toFixed(1)
  return `${text} ${FILE_SIZE_UNITS[unitIndex]}`
}

/** 完整 MIME 串 → 中文名；按精确命中优先，再按主类型前缀。 */
const MIME_EXACT: Record<string, string> = {
  'application/pdf': 'PDF 文档',
  'application/zip': '压缩包',
  'application/x-tar': '压缩包',
  'application/gzip': '压缩包',
  'application/x-7z-compressed': '压缩包',
  'application/x-rar-compressed': '压缩包',
  'application/vnd.rar': '压缩包',
  'application/msword': 'Word 文档',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word 文档',
  'application/vnd.ms-excel': 'Excel 表格',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel 表格',
  'application/vnd.ms-powerpoint': 'PPT 演示',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PPT 演示',
  'application/json': 'JSON 数据'
}

const MIME_PREFIX: ReadonlyArray<readonly [string, string]> = [
  ['image/', '图片'],
  ['audio/', '音频'],
  ['video/', '视频'],
  ['text/', '文本'],
  ['font/', '字体']
]

/** MIME 串 → 中文名；映射表没有的类型原样显示。 */
export function mimeTypeText(mime: string | null | undefined): string {
  const raw = (mime ?? '').trim()
  if (raw === '') return '—'
  const exact = MIME_EXACT[raw.toLowerCase()]
  if (exact !== undefined) return exact
  const prefix = MIME_PREFIX.find(([head]) => raw.toLowerCase().startsWith(head))
  return prefix === undefined ? raw : prefix[1]
}

/** 'YYYY-MM-DD HH:mm:ss' 截到分钟；无法识别的值原样返回。 */
export function formatMinute(value: string | null | undefined): string {
  const text = (value ?? '').trim()
  if (!/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/.test(text)) return text === '' ? '—' : text
  return text.slice(0, 16).replace('T', ' ')
}
