import { useEffect, useRef, useState } from 'react';

import { Editor, Toolbar } from '@wangeditor/editor-for-react';
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor';
import '@wangeditor/editor/dist/css/style.css';

export interface RichTextEditorProps {
  /** HTML 字符串；组件按 defaultHtml 初始化，外部更新请用 key 重挂载。 */
  defaultHtml?: string;
  onChange: (html: string) => void;
  placeholder?: string;
  disabled?: boolean;
}

// 工具栏白名单：只留公告常用的排版项；「上传图片」不在列——附件接口只回
// file_id，没有可直接给 <img> 用的访问地址契约，配图只支持插入网络图片地址。
const TOOLBAR_KEYS = [
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
  'insertLink',
  'clearStyle',
  '|',
  'undo',
  'redo',
] as const;

/**
 * 正文编辑器（WangEditor 5，MIT）。工具栏用 toolbarKeys 白名单收敛到常用项。
 */
export function RichTextEditor({ defaultHtml, onChange, placeholder, disabled }: RichTextEditorProps) {
  const [editor, setEditor] = useState<IDomEditor | null>(null);
  // 卸载销毁用的实例引用。不能在依赖 [editor] 的 effect 清理里做
  // destroy + setEditor(null)：destroy 会触发编辑器内部状态归零并重建
  // 新实例，onCreated 又把新实例写回 state，setEditor(null) 让实例
  // 反复销毁/重建，最终「Maximum update depth exceeded」卡死页面。
  const editorRef = useRef<IDomEditor | null>(null);

  const handleCreated = (instance: IDomEditor) => {
    editorRef.current = instance;
    setEditor(instance);
  };

  // 只在组件卸载时销毁实例，否则 DOM、document 级监听与内部状态泄漏。
  useEffect(() => {
    return () => {
      editorRef.current?.destroy();
      editorRef.current = null;
    };
  }, []);

  const editorConfig: Partial<IEditorConfig> = {
    placeholder,
    readOnly: disabled,
  };

  const toolbarConfig: Partial<IToolbarConfig> = {
    toolbarKeys: [...TOOLBAR_KEYS],
  };

  return (
    <div className="overflow-hidden rounded-md border border-[var(--semi-color-border)]">
      <Toolbar editor={editor} defaultConfig={toolbarConfig} mode="default" />
      <Editor
        defaultHtml={defaultHtml}
        onCreated={handleCreated}
        onChange={(e) => onChange(e.getHtml())}
        defaultConfig={editorConfig}
        mode="default"
        style={{ minHeight: 320 }}
      />
    </div>
  );
}
