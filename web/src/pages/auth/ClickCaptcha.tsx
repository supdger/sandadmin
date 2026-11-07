import { useRef, useState } from 'react';

import { Button, Spin, Typography } from '@douyinfe/semi-ui';
import { IconRefresh } from '@douyinfe/semi-icons';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { getCaptcha, verifyCaptcha } from '@/api/auth';
import { SystemErrorNotice } from '@/components/SystemErrorNotice';

const CHAR_COUNT = 3;

export interface ClickCaptchaProps {
  /** Called once the backend accepts the clicked points. */
  onVerified: (captchaId: string, points: number[]) => void;
}

/**
 * Self-hosted click-to-select captcha ("自研极简点选验证码"): the mock
 * backend returns an inline SVG with scattered characters and keeps the
 * expected centers; the user clicks them in order and the points are
 * verified server-side. No third-party service is involved.
 */
export function ClickCaptcha({ onVerified }: ClickCaptchaProps) {
  const { t } = useTranslation('auth');
  const boxRef = useRef<HTMLDivElement>(null);
  const [epoch, setEpoch] = useState(1);
  const [points, setPoints] = useState<number[]>([]);
  const [verifying, setVerifying] = useState(false);
  const [passed, setPassed] = useState(false);
  const [failed, setFailed] = useState(false);

  const captchaQuery = useQuery({
    queryKey: ['auth', 'captcha', epoch],
    queryFn: getCaptcha,
    retry: false,
    // Captchas are single-use server-side — never serve a stale image.
    gcTime: 0,
    staleTime: 0,
  });
  const captcha = captchaQuery.data;
  const ready = Boolean(captcha) && !verifying && !passed;

  const refresh = () => {
    setPoints([]);
    setFailed(false);
    setPassed(false);
    setEpoch((n) => n + 1);
  };

  const handleClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!captcha || !ready) return;
    const rect = boxRef.current?.getBoundingClientRect();
    const x = Math.round(e.clientX - (rect?.left ?? 0));
    const y = Math.round(e.clientY - (rect?.top ?? 0));
    const next = [...points, x, y];
    setPoints(next);

    if (next.length === CHAR_COUNT * 2) {
      setVerifying(true);
      verifyCaptcha(captcha.captchaId, next)
        .then((ok) => {
          setVerifying(false);
          if (ok) {
            setPassed(true);
            onVerified(captcha.captchaId, next);
          } else {
            setFailed(true);
            setEpoch((n) => n + 1);
            setPoints([]);
          }
        })
        .catch(() => {
          setVerifying(false);
          setFailed(true);
          setEpoch((n) => n + 1);
          setPoints([]);
        });
    }
  };

  return (
    <div data-testid="click-captcha" className="flex flex-col gap-2">
      <Typography.Text type="secondary" size="small">
        {t('login.captchaTip')}
      </Typography.Text>
      <div
        ref={boxRef}
        data-testid="captcha-box"
        onClick={handleClick}
        className="relative self-start"
        style={{ cursor: ready ? 'pointer' : 'default' }}
      >
        {captchaQuery.isError ? (
          <div className="flex items-center justify-center" style={{ width: 300, height: 160 }}>
            <SystemErrorNotice
              error={captchaQuery.error}
              onRetry={() => void captchaQuery.refetch()}
              fallback={<Spin />}
            />
          </div>
        ) : captcha ? (
          <img
            data-testid="captcha-image"
            data-captcha-id={captcha.captchaId}
            src={captcha.imageDataUrl}
            alt={t('login.captchaTip')}
            draggable={false}
            style={{ display: 'block', userSelect: 'none' }}
          />
        ) : (
          <div className="flex items-center justify-center" style={{ width: 300, height: 160 }}>
            <Spin />
          </div>
        )}
        {Array.from({ length: Math.floor(points.length / 2) }, (_, i) => (
          <span
            key={i}
            className="absolute flex items-center justify-center"
            style={{
              left: points[i * 2] - 11,
              top: points[i * 2 + 1] - 11,
              width: 22,
              height: 22,
              borderRadius: 'var(--semi-border-radius-circle)',
              background: 'var(--semi-color-primary)',
              color: 'var(--semi-color-white)',
              fontSize: 'var(--semi-font-size-small)',
              pointerEvents: 'none',
            }}
          >
            {i + 1}
          </span>
        ))}
      </div>
      <div className="flex items-center gap-2">
        <Button
          size="small"
          icon={<IconRefresh />}
          onClick={refresh}
          disabled={captchaQuery.isPending || verifying}
        >
          {t('login.captchaRefresh')}
        </Button>
        {verifying ? (
          <Typography.Text size="small" type="secondary">
            {t('login.captchaVerifying')}
          </Typography.Text>
        ) : null}
        {passed ? (
          <Typography.Text size="small" type="success" data-testid="captcha-ok">
            {t('login.captchaOk')}
          </Typography.Text>
        ) : null}
        {failed ? (
          <Typography.Text size="small" type="danger">
            {t('login.captchaFailed')}
          </Typography.Text>
        ) : null}
      </div>
    </div>
  );
}
