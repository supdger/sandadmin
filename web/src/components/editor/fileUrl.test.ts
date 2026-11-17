import { describe, expect, it } from 'vitest';

import { fileDownloadUrl } from './fileUrl';

describe('fileDownloadUrl', () => {
  it('builds the v1 download url under the api base', () => {
    expect(fileDownloadUrl('file-001')).toBe('/api/admin/v1/files/file-001/download');
  });

  it('escapes unsafe characters in the id', () => {
    expect(fileDownloadUrl('a b/c')).toBe('/api/admin/v1/files/a%20b%2Fc/download');
  });
});
