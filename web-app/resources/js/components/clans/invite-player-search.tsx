import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { IconCopy, IconCheck } from '@tabler/icons-react';
import { toast } from 'sonner';

interface InvitePlayerSearchProps {
  inviteLink: string;
}

export function InvitePlayerSearch({ inviteLink }: InvitePlayerSearchProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [copied, setCopied] = useState(false);

  const handleCopyInviteLink = async () => {
    try {
      const inviteUrl = `${window.location.origin}/clans/join?invite=${inviteLink}`;
      await navigator.clipboard.writeText(inviteUrl);
      setCopied(true);
      toast.success('Invite link copied to clipboard');
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      toast.error('Failed to copy invite link');
    }
  };

  const handleSearch = () => {
    // For now, just copy the invite link
    // In the future, this could search for players and allow direct invitation
    handleCopyInviteLink();
  };

  return (
    <div className="space-y-2">
      <Label htmlFor="player-search">Invite Player</Label>
      <div className="flex items-center gap-2">
        <Input
          id="player-search"
          type="text"
          placeholder="Search by name or Steam ID..."
          value={searchQuery}
          onChange={e => setSearchQuery(e.target.value)}
          onKeyDown={e => {
            if (e.key === 'Enter') {
              handleSearch();
            }
          }}
        />
        <Button
          type="button"
          variant="outline"
          onClick={handleCopyInviteLink}
          className="flex items-center gap-2"
        >
          {copied ? (
            <>
              <IconCheck className="h-4 w-4" />
              Copied
            </>
          ) : (
            <>
              <IconCopy className="h-4 w-4" />
              Copy Link
            </>
          )}
        </Button>
      </div>
      <p className="text-xs text-muted-foreground">
        Search for a player and share the invite link with them to join the clan
      </p>
    </div>
  );
}
