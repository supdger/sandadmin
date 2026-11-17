import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import '@/i18n/i18n';
import { SvgIcon } from './SvgIcon';

describe('SvgIcon', () => {
  it('renders a registered icon by name', () => {
    const { container } = render(<SvgIcon name="home" />);
    expect(container.querySelector('svg')).not.toBeNull();
  });

  it('falls back to the default icon for unknown names', () => {
    const { container } = render(<SvgIcon name="definitely-not-registered" />);
    expect(container.querySelector('svg')).not.toBeNull();
  });

  it('also renders the fallback for empty names', () => {
    const { container } = render(<SvgIcon name={null} />);
    expect(container.querySelector('svg')).not.toBeNull();
  });

  it('marks the icon aria-hidden', () => {
    const { container } = render(<SvgIcon name="user" />);
    expect(container.querySelector('.semi-icon, svg')?.closest('[aria-hidden]')).not.toBeNull();
  });
});
