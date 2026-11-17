import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { env } from '@/config/env';
import { AppLogo } from './AppLogo';

describe('AppLogo', () => {
  it('展开态渲染图标与系统名', () => {
    render(<AppLogo />);
    expect(screen.getByText(env.appName)).toBeInTheDocument();
  });

  it('折叠态只渲染图标不渲染系统名', () => {
    render(<AppLogo collapsed />);
    expect(screen.queryByText(env.appName)).not.toBeInTheDocument();
  });
});
