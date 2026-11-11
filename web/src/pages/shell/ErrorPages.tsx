import { ErrorState, type ErrorKind } from '@/components/ErrorState';

export interface ErrorPageProps {
  kind: Extract<ErrorKind, '403' | '500'>;
}

/**
 * Bare error routes (`/403`, `/500`) rendered without the shell — a missing
 * permission or a broken server must not depend on a working shell to be
 * explainable.
 */
export function ErrorPage({ kind }: ErrorPageProps) {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <ErrorState kind={kind} />
    </div>
  );
}
