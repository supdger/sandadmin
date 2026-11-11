import { useMemo, useRef, useState } from 'react';

import { Button, Form, Modal, Spin, Toast, Typography } from '@douyinfe/semi-ui';
import type { FormApi } from '@douyinfe/semi-ui/lib/es/form';
import type { TFunction } from 'i18next';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';

import { changePassword, getPasswordRule, type PasswordRule } from '@/api/v1/profile';
import { useMe } from '@/hooks/useMe';
import { AdminApiError } from '@/lib/adminClient';
import { adminErrorMessage } from '@/lib/adminError';
import { FIELD_PASSWORD_WIDTH } from '@/components/fieldWidth';

export interface PasswordChangeModalProps {
  visible: boolean;
  onClose: () => void;
}

type PasswordFormValues = {
  old_password: string;
  new_password: string;
  confirm_password: string;
};

function buildRules(t: TFunction, rule: PasswordRule | undefined): string[] {
  if (!rule) {
    return [];
  }
  const rules: string[] = [t('profile:passwordModal.ruleMinLength', { count: rule.min_length })];
  if (rule.require_letter) {
    rules.push(t('profile:passwordModal.ruleLetter'));
  }
  if (rule.require_digit) {
    rules.push(t('profile:passwordModal.ruleDigit'));
  }
  if (rule.forbid_weak) {
    rules.push(t('profile:passwordModal.ruleWeak'));
  }
  if (rule.forbid_same_as_username) {
    rules.push(t('profile:passwordModal.ruleSameAsUsername'));
  }
  if (rule.forbid_same_as_previous > 0) {
    rules.push(t('profile:passwordModal.ruleHistory', { count: rule.forbid_same_as_previous }));
  }
  return rules;
}

/**
 * Shared change-password dialog (profile page + avatar menu). Client-side
 * checks mirror /auth/password-policy hints; the server remains authoritative
 * and its field errors are mapped back onto the form.
 */
export function PasswordChangeModal({ visible, onClose }: PasswordChangeModalProps) {
  const { t } = useTranslation('profile');
  const me = useMe();
  const formApi = useRef<FormApi<Record<string, unknown>> | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const ruleQuery = useQuery({
    queryKey: ['passwordRule'],
    queryFn: getPasswordRule,
    enabled: visible,
    staleTime: 300_000,
  });
  const rule = ruleQuery.data;

  const schema = useMemo(() => {
    const username = (me.data?.username ?? '').toLowerCase();
    return z
      .object({
        old_password: z.string().min(1, t('passwordModal.oldRequired')),
        new_password: z
          .string()
          .min(rule?.min_length ?? 1, t('passwordModal.tooShort', { count: rule?.min_length ?? 0 }))
          .refine(
            (v) => !rule?.require_letter || /[a-zA-Z]/.test(v),
            t('passwordModal.needLetter'),
          )
          .refine((v) => !rule?.require_digit || /\d/.test(v), t('passwordModal.needDigit'))
          .refine(
            (v) => !(rule?.forbid_same_as_username && username) || v.toLowerCase() !== username,
            t('passwordModal.sameAsUsername'),
          ),
        confirm_password: z.string().min(1, t('passwordModal.required')),
      })
      .refine((d) => d.new_password === d.confirm_password, {
        path: ['confirm_password'],
        message: t('passwordModal.mismatch'),
      });
  }, [rule, me.data?.username, t]);

  const rules = useMemo(() => buildRules(t, rule), [rule, t]);

  const handleClose = () => {
    formApi.current?.reset();
    onClose();
  };

  const handleSubmit = async (values: PasswordFormValues) => {
    const parsed = schema.safeParse(values);
    if (!parsed.success) {
      const shown = new Set<string>();
      for (const issue of parsed.error.issues) {
        const field = String(issue.path[0]);
        if (!shown.has(field)) {
          shown.add(field);
          formApi.current?.setError(field, issue.message);
        }
      }
      return;
    }

    setSubmitting(true);
    try {
      await changePassword({
        old_password: parsed.data.old_password,
        new_password: parsed.data.new_password,
      });
      Toast.success(t('passwordModal.success'));
      formApi.current?.reset();
      onClose();
    } catch (e) {
      if (e instanceof AdminApiError && e.fields) {
        for (const [field, messages] of Object.entries(e.fields)) {
          formApi.current?.setError(field, messages[0] ?? '');
        }
      }
      if (!(e instanceof AdminApiError && e.fields && Object.keys(e.fields).length > 0)) {
        Toast.error(adminErrorMessage(e, t('common:errors.system.message')));
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal
      visible={visible}
      title={t('passwordModal.title')}
      width={420}
      footer={null}
      onCancel={handleClose}
      closeOnEsc
    >
      {ruleQuery.isPending ? (
        <div className="flex justify-center py-8">
          <Spin />
        </div>
      ) : (
        <>
          {rules.length > 0 ? (
            <div className="pb-3">
              <Typography.Text strong size="small">
                {t('passwordModal.policyTitle')}
              </Typography.Text>
              <ul className="m-0 flex flex-col gap-1 pt-2" style={{ listStyle: 'none', padding: 0 }}>
                {rules.map((ruleText) => (
                  <li key={ruleText}>{ruleText}</li>
                ))}
              </ul>
            </div>
          ) : null}
          <Form
            onSubmit={(values) => void handleSubmit(values as PasswordFormValues)}
            getFormApi={(api) => {
              formApi.current = api as FormApi<Record<string, unknown>>;
            }}
          >
            <Form.Input
              field="old_password"
              type="password"
              style={{ width: FIELD_PASSWORD_WIDTH }}
              label={t('passwordModal.oldPassword')}
              placeholder={t('passwordModal.oldPasswordPlaceholder')}
              rules={[{ required: true, message: t('passwordModal.oldRequired') }]}
            />
            <Form.Input
              field="new_password"
              type="password"
              style={{ width: FIELD_PASSWORD_WIDTH }}
              label={t('passwordModal.newPassword')}
              placeholder={t('passwordModal.newPasswordPlaceholder')}
              rules={[{ required: true, message: t('passwordModal.required') }]}
            />
            <Form.Input
              field="confirm_password"
              type="password"
              style={{ width: FIELD_PASSWORD_WIDTH }}
              label={t('passwordModal.confirmPassword')}
              placeholder={t('passwordModal.confirmPasswordPlaceholder')}
              rules={[{ required: true, message: t('passwordModal.required') }]}
            />
            <Button
              htmlType="submit"
              type="primary"
              theme="solid"
              block
              loading={submitting}
            >
              {t('passwordModal.submit')}
            </Button>
          </Form>
        </>
      )}
    </Modal>
  );
}
