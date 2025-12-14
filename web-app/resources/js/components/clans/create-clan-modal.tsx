import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { IconPlus, IconAlertCircle } from '@tabler/icons-react';
import { api } from '@/lib/api';

interface Clan {
  id: number;
  name: string;
  tag: string | null;
  invite_link: string;
  owner: {
    id: number;
    name: string;
    email: string;
  };
  members: any[];
  created_at: string;
  updated_at: string;
}

interface CreateClanResponse {
  message: string;
  data: Clan;
}

interface CreateClanModalProps {
  onSuccess?: (clan: Clan) => void;
}

export function CreateClanModal({ onSuccess }: CreateClanModalProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [name, setName] = useState('');
  const [tag, setTag] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!name.trim()) {
      setError('Clan name is required');
      return;
    }

    if (!tag.trim()) {
      setError('Clan tag is required');
      return;
    }

    if (tag.length > 4) {
      setError('Clan tag must be 4 characters or less');
      return;
    }

    // Validate alphanumeric for name
    if (name && !/^[a-zA-Z0-9]+$/.test(name.trim())) {
      setError('Clan name must contain only letters and numbers');
      return;
    }

    // Validate alphanumeric for tag
    if (tag && !/^[a-zA-Z0-9]+$/.test(tag.trim())) {
      setError('Clan tag must contain only letters and numbers');
      return;
    }

    setIsCreating(true);

    try {
      const response = await api.post<CreateClanResponse>(
        '/clans',
        {
          name: name.trim(),
          tag: tag.trim(),
        },
        {
          requireAuth: true,
        }
      );

      if (response.data.data) {
        setIsOpen(false);
        setName('');
        setTag('');
        setError(null);
        if (onSuccess) {
          onSuccess(response.data.data);
        }
      }
    } catch (err: unknown) {
      console.error('Error creating clan:', err);
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to create clan';
      setError(errorMessage);
    } finally {
      setIsCreating(false);
    }
  };

  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    if (!open) {
      // Reset state when closing
      setName('');
      setTag('');
      setError(null);
    }
  };

  return (
    <Sheet open={isOpen} onOpenChange={handleOpenChange}>
      <SheetTrigger asChild>
        <Button
          size="sm"
          className="border bg-background shadow-xs hover:bg-accent hover:text-accent-foreground border-custom-orange text-white"
        >
          <IconPlus className="h-4 w-4 mr-2" />
          Create Clan
        </Button>
      </SheetTrigger>
      <SheetContent>
        <form onSubmit={handleSubmit}>
          <SheetHeader>
            <SheetTitle>Create New Clan</SheetTitle>
            <SheetDescription>
              Create a new clan to track matches and leaderboards with your
              team.
            </SheetDescription>
          </SheetHeader>

          <div className="space-y-4 py-4 px-4">
            <div className="space-y-2">
              <Label htmlFor="clan-name">Clan Name *</Label>
              <Input
                id="clan-name"
                type="text"
                placeholder="Enter clan name"
                value={name}
                onChange={e => {
                  const value = e.target.value;
                  // Only allow alphanumeric characters
                  if (value === '' || /^[a-zA-Z0-9]*$/.test(value)) {
                    setName(value);
                    setError(null);
                  }
                }}
                disabled={isCreating}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="clan-tag">Clan Tag *</Label>
              <Input
                id="clan-tag"
                type="text"
                placeholder="e.g., MC"
                value={tag}
                onChange={e => {
                  const value = e.target.value.toUpperCase();
                  // Only allow alphanumeric characters and max 4 characters
                  if (
                    (value === '' || /^[a-zA-Z0-9]*$/.test(value)) &&
                    value.length <= 4
                  ) {
                    setTag(value);
                    setError(null);
                  }
                }}
                disabled={isCreating}
                maxLength={4}
                required
              />
              <p className="text-xs text-muted-foreground">
                Maximum 4 characters, letters and numbers only
              </p>
            </div>

            {error && (
              <Alert variant="destructive">
                <IconAlertCircle className="h-4 w-4" />
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <SheetFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => handleOpenChange(false)}
              disabled={isCreating}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={isCreating}>
              {isCreating ? 'Creating...' : 'Create Clan'}
            </Button>
          </SheetFooter>
        </form>
      </SheetContent>
    </Sheet>
  );
}
