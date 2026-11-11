import { useState } from 'react';

import { useQuery, useQueryClient } from '@tanstack/react-query';

import { getAnnouncementPopup } from '@/api/v1/notifications';
import { ImportantAnnouncementModal } from '@/components/ImportantAnnouncementModal';

const POPUP_KEY = ['notifications', 'announcements-popup'] as const;

/** Fetches important unread announcements once per session and pops them in order. */
export function AnnouncementPopup() {
  const queryClient = useQueryClient();
  const popup = useQuery({
    queryKey: POPUP_KEY,
    queryFn: getAnnouncementPopup,
    staleTime: 60_000,
    retry: 1,
  });
  const [closed, setClosed] = useState(false);

  const items = popup.data?.items ?? [];
  if (closed || items.length === 0) {
    return null;
  }
  return (
    <ImportantAnnouncementModal
      items={items}
      onCloseAll={() => {
        setClosed(true);
        void queryClient.invalidateQueries({ queryKey: POPUP_KEY });
      }}
    />
  );
}
