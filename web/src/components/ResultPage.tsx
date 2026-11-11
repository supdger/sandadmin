import type { JSX, ReactNode } from 'react';

import { Button, Empty as SemiEmpty } from '@douyinfe/semi-ui';
import {
  IllustrationFailure,
  IllustrationFailureDark,
  IllustrationNoAccess,
  IllustrationNoAccessDark,
  IllustrationNotFound,
  IllustrationNotFoundDark,
  IllustrationSuccess,
  IllustrationSuccessDark,
} from '@douyinfe/semi-illustrations';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

export type ResultStatus = 'success' | 'fail' | '403' | '404' | '500';

export interface ResultPageProps {
  status: 'success' | 'fail' | '403' | '404' | '500';
  title: string;
  description?: string;
  extra?: ReactNode; // 操作按钮区，调用方传入
}

const ILLUSTRATIONS: Record<ResultStatus, { light: ReactNode; dark: ReactNode }> = {
  success: { light: <IllustrationSuccess />, dark: <IllustrationSuccessDark /> },
  fail: { light: <IllustrationFailure />, dark: <IllustrationFailureDark /> },
  '403': { light: <IllustrationNoAccess />, dark: <IllustrationNoAccessDark /> },
  '404': { light: <IllustrationNotFound />, dark: <IllustrationNotFoundDark /> },
  '500': { light: <IllustrationFailure />, dark: <IllustrationFailureDark /> },
};

/**
 * Result page shared by flows like install/finish: status illustration +
 * caller-provided title/description + an action slot. When `extra` is not
 * given, a default "back to home" button is rendered.
 */
export function ResultPage({ status, title, description, extra }: ResultPageProps): JSX.Element {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const illustration = ILLUSTRATIONS[status];

  return (
    <div className="flex items-center justify-center py-16">
      <SemiEmpty
        image={illustration.light}
        darkModeImage={illustration.dark}
        title={title}
        description={description}
      >
        <div className="flex items-center justify-center gap-2">
          {extra ?? (
            <Button theme="solid" onClick={() => navigate('/')}>
              {t('actions.backHome')}
            </Button>
          )}
        </div>
      </SemiEmpty>
    </div>
  );
}
