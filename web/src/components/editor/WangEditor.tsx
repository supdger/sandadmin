import { useEffect, useRef, useState } from 'react';

import { Editor, Toolbar } from '@wangeditor/editor-for-react';
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor';
import '@wangeditor/editor/dist/css/style.css';
import { useTranslation } from 'react-i18next';

export interface WangEditorProps {
  /** 受控 HTML；外部改值会同步进编辑器（与编辑器自身产出不同时才 setHtml）。 */
  value?: string;
  onChange?: (html: string) => void;
  placeholder?: string;
  /** 只读模式：隐藏工具栏且不可编辑。 */
  readOnly?: boolean;
  /** 工具栏白名单；缺省用常用排版项。 */
  toolbarKeys?: string[];
  /** 编辑器实例回调（图库选图等要直接操作实例时用）。 */
  onCreated?: (editor: IDomEditor) => void;
  minHeight?: number;
}

const DEFAULT_TOOLBAR_KEYS = [
  'headerSelect',
  'bold',
  'italic',
  'underline',
  'color',
  '|',
  'justifyLeft',
  'justifyCenter',
  'justifyRight',
  'bulletedList',
  'numberedList',
  'blockquote',
  '|',
  'insertImage',
  'insertLink',
  'clearStyle',
  '|',
  'undo',
  'redo',
] as const;

/**
 * WangEditor 5 受控封装。lastHtml 记录编辑器自身最近一次产出，
 * 只在 value 与之不同步（外部赋值）时才 setHtml，避免输入中被回刷。
 */
export function WangEditor({
  value = '',
  onChange,
  placeholder,
  readOnly,
  toolbarKeys,
  onCreated,
  minHeight = 280,
}: WangEditorProps) {
  const { t } = useTranslation('feForm');
  const [editor, setEditor] = useState<IDomEditor | null>(null);
  // 卸载销毁只认 ref，不能用 [editor] 清理：destroy 触发内部重建会让
  // onCreated 写回新实例，setEditor(null) 造成销毁/重建死循环。
  const editorRef = useRef<IDomEditor | null>(null);
  const lastHtml = useRef<string>(value);

  const handleCreated = (instance: IDomEditor) => {
    editorRef.current = instance;
    setEditor(instance);
    if (readOnly) {
      instance.disable();
    }
    onCreated?.(instance);
  };

  useEffect(() => {
    return () => {
      editorRef.current?.destroy();
      editorRef.current = null;
    };
  }, []);

  useEffect(() => {
    if (!editor) {
      return;
    }
    if (readOnly) {
      editor.disable();
    } else {
      editor.enable();
    }
  }, [editor, readOnly]);

  useEffect(() => {
    if (editor && value !== lastHtml.current) {
      lastHtml.current = value;
      editor.setHtml(value);
    }
  }, [editor, value]);

  const editorConfig: Partial<IEditorConfig> = {
    placeholder: placeholder ?? t('editor.placeholder'),
    readOnly,
  };

  const toolbarConfig: Partial<IToolbarConfig> = {
    toolbarKeys: [...(toolbarKeys ?? DEFAULT_TOOLBAR_KEYS)],
  };

  return (
    <div className="overflow-hidden rounded-md border border-[var(--semi-color-border)]">
      {readOnly ? null : <Toolbar editor={editor} defaultConfig={toolbarConfig} mode="default" />}
      <Editor
        defaultHtml={value}
        onCreated={handleCreated}
        onChange={(instance) => {
          const html = instance.getHtml();
          lastHtml.current = html;
          onChange?.(html);
        }}
        defaultConfig={editorConfig}
        mode="default"
        style={{ minHeight }}
      />
    </div>
  );
}
