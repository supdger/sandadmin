import { useCallback, useState } from 'react';

import { Toast } from '@douyinfe/semi-ui';
import { useMutation } from '@tanstack/react-query';

import i18n from '@/i18n/i18n';
import { adminErrorMessage } from '@/lib/adminError';
import { useFormIdempotencyKey } from './useFormIdempotencyKey';

export type CrudMode = 'create' | 'edit';

export interface CrudModalOptions<TRecord, TValues> {
  /** 提交实现：create 时 record 为 null；幂等键由 hook 供（每次打开一把）。 */
  submit: (values: TValues, record: TRecord | null, idempotencyKey: string) => Promise<unknown>;
  onSuccess?: (mode: CrudMode, record: TRecord | null) => void;
  /** 自定义失败处理；缺省 Toast 服务端 message。 */
  onError?: (error: unknown) => void;
  errorMessage?: string;
  /** 成功后保持打开（连续新增场景）；默认成功后关闭。 */
  keepOpenOnSuccess?: boolean;
}

export interface CrudModalResult<TRecord, TValues> {
  visible: boolean;
  mode: CrudMode | null;
  record: TRecord | null;
  submitting: boolean;
  /** 最近一次提交失败，交给表单做 fields 回显；打开/提交时清零。 */
  submitError: unknown;
  /** 本次打开会话的幂等键；submit 已代传，只读场景备用。 */
  idempotencyKey: string;
  openCreate: () => void;
  openEdit: (record: TRecord) => void;
  close: () => void;
  submit: (values: TValues) => void;
}

/**
 * CRUD 弹层状态与提交生命周期：开/关、create/edit 区分、提交中防重、
 * 幂等键随打开轮换、失败留 submitError 给表单回显 fields。
 */
export function useCrudModal<TRecord, TValues>(
  options: CrudModalOptions<TRecord, TValues>,
): CrudModalResult<TRecord, TValues> {
  const [state, setState] = useState<{
    visible: boolean;
    mode: CrudMode | null;
    record: TRecord | null;
  }>({ visible: false, mode: null, record: null });
  const [submitError, setSubmitError] = useState<unknown>(null);

  const idempotencyKey = useFormIdempotencyKey(state.visible);

  const close = useCallback(() => {
    setState({ visible: false, mode: null, record: null });
    setSubmitError(null);
  }, []);

  const mutation = useMutation({
    mutationFn: (values: TValues) => options.submit(values, state.record, idempotencyKey),
    onSuccess: () => {
      setSubmitError(null);
      if (!options.keepOpenOnSuccess) {
        close();
      }
      options.onSuccess?.(state.mode ?? 'create', state.record);
    },
    onError: (error) => {
      setSubmitError(error);
      if (options.onError) {
        options.onError(error);
      } else {
        Toast.error(
          adminErrorMessage(error, options.errorMessage ?? i18n.t('feForm:crud.saveFailed')),
        );
      }
    },
  });

  const openCreate = useCallback(() => {
    setState({ visible: true, mode: 'create', record: null });
    setSubmitError(null);
  }, []);

  const openEdit = useCallback((record: TRecord) => {
    setState({ visible: true, mode: 'edit', record });
    setSubmitError(null);
  }, []);

  const submit = useCallback(
    (values: TValues) => {
      if (mutation.isPending) {
        return;
      }
      setSubmitError(null);
      mutation.mutate(values);
    },
    [mutation],
  );

  return {
    visible: state.visible,
    mode: state.mode,
    record: state.record,
    submitting: mutation.isPending,
    submitError,
    idempotencyKey,
    openCreate,
    openEdit,
    close,
    submit,
  };
}
